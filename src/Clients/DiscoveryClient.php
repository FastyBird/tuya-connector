<?php declare(strict_types = 1);

/**
 * DiscoveryClient.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 * @since          0.13.0
 *
 * @date           27.08.22
 */

namespace FastyBird\TuyaConnector\Clients;

use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\TuyaConnector\API;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Types;
use Nette;
use Nette\Utils;
use Psr\Log;
use React\Async;
use React\Datagram;
use React\EventLoop;
use SplObjectStorage;
use Throwable;

/**
 * Devices discovery client
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DiscoveryClient
{

	use Nette\SmartObject;

	private const UDP_BIND_IP = '0.0.0.0';
	private const UDP_PORT = [
		Types\DeviceProtocolVersionType::VERSION_V31 => 6666,
		Types\DeviceProtocolVersionType::VERSION_V33 => 6667,
	];
	private const UDP_TIMEOUT = 5;

	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	/** @var string[] */
	private array $processedProtocols = [];

	/** @var SplObjectStorage<Entities\Messages\DiscoveredLocalDeviceEntity, null> */
	private SplObjectStorage $discoveredLocalDevices;

	/** @var string|null */
	private ?string $devicesUid = null;

	/** @var bool */
	private bool $ongoingApiRequest = false;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $handlerTimer;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Datagram\SocketInterface|null */
	private ?Datagram\SocketInterface $connection = null;

	/** @var Datagram\Factory */
	private Datagram\Factory $serverFactory;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var API\OpenApiApi */
	private API\OpenApiApi $openApiApi;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param API\OpenApiApiFactory $openApiApiFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		API\OpenApiApiFactory $openApiApiFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();

		$this->serverFactory = new Datagram\Factory($this->eventLoop);
		$this->openApiApi = $openApiApiFactory->create($this->connector);
	}

	/**
	 * @return void
	 */
	public function discover(): void
	{
		$this->discoveredLocalDevices = new SplObjectStorage();
		$this->devicesUid = null;
		$this->ongoingApiRequest = false;

		$this->registerHandler();
	}

	/**
	 * @return void
	 */
	public function disconnect(): void
	{
		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);
		}
	}

	/**
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return false;
	}

	/**
	 * @return void
	 */
	private function registerHandler(): void
	{
		$this->handlerTimer = $this->eventLoop->addTimer(
			self::HANDLER_PROCESSING_INTERVAL,
			function (): void {
				$this->handleCommunication();
			}
		);
	}

	/**
	 * @return void
	 *
	 * @throws Throwable
	 */
	private function handleCommunication(): void
	{
		if ($this->ongoingApiRequest) {
			$this->registerHandler();

			return;
		}

		// Socket connection is open, we are waiting for some reply from devices
		if ($this->connection !== null) {
			$this->registerHandler();

			return;
		}

		// Process all known protocols
		foreach ([/*Types\DeviceProtocolVersionType::VERSION_V31,*/
					 Types\DeviceProtocolVersionType::VERSION_V33,
				 ] as $protocolVersion) {
			if (in_array($protocolVersion, $this->processedProtocols, true)) {
				continue;
			}

			$this->logger->debug(
				'Starting local devices discovery',
				[
					'source'   => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'     => 'discovery-client',
					'protocol' => $protocolVersion,
				]
			);

			try {
				/** @var Datagram\Socket $server */
				$server = Async\await($this->serverFactory->createServer(
					self::UDP_BIND_IP . ':' . self::UDP_PORT[$protocolVersion]
				));

				$server->on('message', function (string $message, string $remote): void {
					$this->handleFoundLocalDevice($message);
				});

				$this->connection = $server;
			} catch (Throwable $ex) {
				$this->logger->error(
					'Could not create local UDP server',
					[
						'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'      => 'discovery-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
						'protocol'  => $protocolVersion,
					]
				);
			}

			// Searching timeout
			$this->eventLoop->addTimer(self::UDP_TIMEOUT, function (): void {
				$this->connection?->close();
				$this->connection = null;
			});

			$this->processedProtocols[] = $protocolVersion;

			$this->registerHandler();

			return;
		}

		if ($this->discoveredLocalDevices->count() > 0 && $this->devicesUid === null) {
			$this->discoveredLocalDevices->rewind();

			$testLocalDevice = $this->discoveredLocalDevices->current();

			try {
				$this->ongoingApiRequest = true;

				/** @var Entities\API\UserDeviceDetailEntity $deviceDetail */
				$deviceDetail = Async\await($this->openApiApi->getUserDeviceDetail($testLocalDevice->getId()));
				var_dump($deviceDetail->toArray());

				$this->devicesUid = $deviceDetail->getUid();

			} catch (Throwable $ex) {
				$this->logger->error(
					'Devices uid could not be obtained from device detail',
					[
						'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'      => 'discovery-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]
				);

			} finally {
				$this->ongoingApiRequest = false;

				$this->discoveredLocalDevices->detach($testLocalDevice);
			}

			$this->registerHandler();

			return;
		}

		if ($this->devicesUid === null) {
			$this->logger->warning(
				'Devices uid could not be obtained from device detail',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'discovery-client',
				]
			);

			return;
		}

		/** @var Entities\API\UserDeviceDetailEntity[] $userDevices */
		$userDevices = Async\await($this->openApiApi->getUserDevices($this->devicesUid));

		/** @var Entities\API\UserDeviceFactoryInfoEntity[] $userDevicesFactoryInfos */
		$userDevicesFactoryInfos = Async\await($this->openApiApi->getUserDevicesFactoryInfos(
			array_map(function (Entities\API\UserDeviceDetailEntity $userDevice): string {
				return $userDevice->getId();
			}, $userDevices)
		));

		$this->handleFoundCloudDevices($userDevices, $userDevicesFactoryInfos);
	}

	/**
	 * @param string $packet
	 *
	 * @return void
	 */
	private function handleFoundLocalDevice(string $packet): void
	{
		$encryptedPacket = openssl_decrypt(
			substr($packet, 20, -8),
			'AES-128-ECB',
			md5('yGAdlopoPVldABfn', true),
			OPENSSL_RAW_DATA
		);

		if ($encryptedPacket === false) {
			$this->logger->error(
				'Received invalid UDP packet. Received data could not be decrypted',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'discovery-client',
				]
			);

			return;
		}

		try {
			$decoded = Utils\Json::decode($encryptedPacket, Utils\Json::FORCE_ARRAY);

			if (!is_array($decoded)) {
				$this->logger->error(
					'Decoded discovered local message has invalid format',
					[
						'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'   => 'discovery-client',
					]
				);

				return;
			}

			$deviceInfo = Utils\ArrayHash::from($decoded);

			if (
				!$deviceInfo->offsetExists('gwId')
				|| !$deviceInfo->offsetExists('ip')
				|| !$deviceInfo->offsetExists('productKey')
				|| !$deviceInfo->offsetExists('encrypt')
				|| !$deviceInfo->offsetExists('version')
			) {
				$this->logger->error(
					'Decoded discovered local message has invalid format',
					[
						'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'   => 'discovery-client',
					]
				);

				return;
			}

			$this->discoveredLocalDevices->attach(new Entities\Messages\DiscoveredLocalDeviceEntity(
				strval($deviceInfo->offsetGet('gwId')),
				strval($deviceInfo->offsetGet('ip')),
				strval($deviceInfo->offsetGet('productKey')),
				boolval($deviceInfo->offsetGet('encrypt')),
				strval($deviceInfo->offsetGet('version')),
				Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_LOCAL_DISCOVERY)
			));

		} catch (Utils\JsonException $ex) {
			$this->logger->error(
				'Received invalid UDP packet. Received data are not valid JSON string',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'      => 'discovery-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]
			);

			return;
		}
	}

	/**
	 * @param Entities\API\UserDeviceDetailEntity[] $devices
	 * @param Entities\API\UserDeviceFactoryInfoEntity[] $devicesFactoryInfos
	 *
	 * @return void
	 *
	 * @throws Throwable
	 */
	private function handleFoundCloudDevices(
		array $devices,
		array $devicesFactoryInfos
	): void {
		foreach ($devices as $device) {
			$deviceFactoryInfosFiltered = array_filter(
				$devicesFactoryInfos,
				function (Entities\API\UserDeviceFactoryInfoEntity $item) use ($device): bool {
					return $device->getId() === $item->getId();
				}
			);

			$deviceFactoryInfos = count($deviceFactoryInfosFiltered) === 0 ? current($deviceFactoryInfosFiltered) : null;

			/** @var Entities\API\UserDeviceSpecificationsEntity $deviceSpecifications */
			$deviceSpecifications = $this->openApiApi->getUserDeviceSpecifications($device->getId());

			$dataPointsInfos = [];

			foreach ($deviceSpecifications->getFunctions() as $function) {
				if (!array_key_exists($function->getCode(), $dataPointsInfos)) {
					$dataPointsInfos[$function->getCode()] = [
						'function' => null,
						'status'   => null,
					];
				}

				$dataPointsInfos[$function->getCode()]['function'] = $function;
			}

			foreach ($deviceSpecifications->getStatus() as $status) {
				if (!array_key_exists($status->getCode(), $dataPointsInfos)) {
					$dataPointsInfos[$status->getCode()] = [
						'function' => null,
						'status'   => null,
					];
				}

				$dataPointsInfos[$status->getCode()]['status'] = $status;
			}

			$dataPoints = [];

			foreach ($dataPointsInfos as $dataPointInfos) {

			}
		}
	}

}
