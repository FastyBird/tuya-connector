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
use FastyBird\TuyaConnector\Consumers;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Events;
use FastyBird\TuyaConnector\Exceptions;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Nette;
use Nette\Utils;
use Psr\EventDispatcher as PsrEventDispatcher;
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

	/** @var string[] */
	private array $processedProtocols = [];

	/** @var SplObjectStorage<Entities\Messages\DiscoveredLocalDeviceEntity, null> */
	private SplObjectStorage $discoveredLocalDevices;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $handlerTimer = null;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Datagram\SocketInterface|null */
	private ?Datagram\SocketInterface $connection = null;

	/** @var Helpers\ConnectorHelper */
	private Helpers\ConnectorHelper $connectorHelper;

	/** @var API\OpenApiApi */
	private API\OpenApiApi $openApiApi;

	/** @var Consumers\Consumer */
	private Consumers\Consumer $consumer;

	/** @var Datagram\Factory */
	private Datagram\Factory $serverFactory;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var PsrEventDispatcher\EventDispatcherInterface|null */
	private ?PsrEventDispatcher\EventDispatcherInterface $dispatcher;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param API\OpenApiApiFactory $openApiApiFactory
	 * @param Helpers\ConnectorHelper $connectorHelper
	 * @param Consumers\Consumer $consumer
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param PsrEventDispatcher\EventDispatcherInterface|null $dispatcher
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		API\OpenApiApiFactory $openApiApiFactory,
		Helpers\ConnectorHelper $connectorHelper,
		Consumers\Consumer $consumer,
		EventLoop\LoopInterface $eventLoop,
		?PsrEventDispatcher\EventDispatcherInterface $dispatcher,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->connectorHelper = $connectorHelper;
		$this->consumer = $consumer;
		$this->eventLoop = $eventLoop;
		$this->dispatcher = $dispatcher;

		$this->logger = $logger ?? new Log\NullLogger();

		$this->serverFactory = new Datagram\Factory($this->eventLoop);
		$this->openApiApi = $openApiApiFactory->create($this->connector);
	}

	/**
	 * @return void
	 *
	 * @throws Throwable
	 */
	public function discover(): void
	{
		$this->discoveredLocalDevices = new SplObjectStorage();

		$mode = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifierType::get(Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE)
		);

		if ($mode === Types\ClientModeType::MODE_CLOUD) {
			$this->discoverCloudDevices();

		} elseif ($mode === Types\ClientModeType::MODE_LOCAL) {
			$this->discoverLocalDevices();
		}
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
	 * @return void
	 *
	 * @throws Throwable
	 */
	private function discoverLocalDevices(): void
	{
		$knownProtocolsVersions = [
			Types\DeviceProtocolVersionType::VERSION_V31,
			Types\DeviceProtocolVersionType::VERSION_V33,
		];

		// Process all known protocols
		foreach ($knownProtocolsVersions as $protocolVersion) {
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
					$this->handleDiscoveredLocalDevice($message);
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

				$this->discoverLocalDevices();
			});

			$this->processedProtocols[] = $protocolVersion;

			return;
		}

		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);
		}

		$this->discoveredLocalDevices->rewind();

		$devices = [];

		foreach ($this->discoveredLocalDevices as $device) {
			$devices[] = $device;
		}

		$this->discoveredLocalDevices = new SplObjectStorage();

		$this->handleFoundLocalDevices($devices);

		$this->dispatcher?->dispatch(new Events\DiscoveryFinishedEvent());
	}

	/**
	 * @return void
	 *
	 * @throws Throwable
	 */
	private function discoverCloudDevices(): void
	{
		$this->logger->debug(
			'Starting cloud devices discovery',
			[
				'source'   => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'     => 'discovery-client',
			]
		);

		try {
			/** @var Entities\API\DeviceInformationEntity[] $devices */
			$devices = Async\await($this->openApiApi->getDevices([
				'source_id'   => $this->connectorHelper->getConfiguration($this->connector->getId(), Types\ConnectorPropertyIdentifierType::get(Types\ConnectorPropertyIdentifierType::IDENTIFIER_UID)),
				'source_type' => 'tuyaUser',
			]));

		} catch (Exceptions\OpenApiCallException $ex) {
			$this->logger->error(
				'Loading devices from cloud failed',
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

		try {
			/** @var Entities\API\DeviceFactoryInfosEntity[] $devicesFactoryInfos */
			$devicesFactoryInfos = Async\await($this->openApiApi->getDevicesFactoryInfos(
				array_map(function (Entities\API\DeviceInformationEntity $userDevice): string {
					return $userDevice->getId();
				}, $devices)
			));

		} catch (Exceptions\OpenApiCallException $ex) {
			$this->logger->error(
				'Loading devices factory infos from cloud failed',
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

		$this->handleFoundCloudDevices($devices, $devicesFactoryInfos);

		$this->dispatcher?->dispatch(new Events\DiscoveryFinishedEvent());
	}

	/**
	 * @param string $packet
	 *
	 * @return void
	 */
	private function handleDiscoveredLocalDevice(string $packet): void
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
	 * @param Entities\Messages\DiscoveredLocalDeviceEntity[] $devices
	 *
	 * @return void
	 */
	private function handleFoundLocalDevices(
		array $devices,
	): void {
		foreach ($devices as $device) {
			$this->consumer->append($device);
		}
	}

	/**
	 * @param Entities\API\DeviceInformationEntity[] $devices
	 * @param Entities\API\DeviceFactoryInfosEntity[] $devicesFactoryInfos
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
			/** @var Entities\API\DeviceFactoryInfosEntity[] $deviceFactoryInfosFiltered */
			$deviceFactoryInfosFiltered = array_values(array_filter(
				$devicesFactoryInfos,
				function (Entities\API\DeviceFactoryInfosEntity $item) use ($device): bool {
					return $device->getId() === $item->getId();
				}
			));

			$deviceFactoryInfos = count($deviceFactoryInfosFiltered) > 0 ? $deviceFactoryInfosFiltered[0] : null;

			$dataPoints = [];

			try {
				/** @var Entities\API\DeviceSpecificationEntity $deviceSpecifications */
				$deviceSpecifications = Async\await($this->openApiApi->getDeviceSpecification($device->getId()));

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

				foreach ($dataPointsInfos as $dataPointInfos) {
					/** @var Entities\API\DeviceSpecificationFunctionEntity|null $dataPointFunction */
					$dataPointFunction = $dataPointInfos['function'];
					/** @var Entities\API\DeviceSpecificationStatusEntity|null $dataPointFunction */
					$dataPointStatus = $dataPointInfos['status'];

					if ($dataPointFunction === null && $dataPointStatus === null) {
						continue;
					}

					$dataPointCode = null;
					$dataPointType = null;
					$dataPointSpecification = '{}';
					$dataPointDataType = Metadata\Types\DataTypeType::get(Metadata\Types\DataTypeType::DATA_TYPE_UNKNOWN);

					if ($dataPointFunction !== null) {
						$dataPointCode = $dataPointFunction->getCode();
						$dataPointType = Utils\Strings::lower($dataPointFunction->getType());
						$dataPointSpecification = $dataPointFunction->getValues();

					} elseif ($dataPointStatus !== null) {
						$dataPointCode = $dataPointStatus->getCode();
						$dataPointType = Utils\Strings::lower($dataPointStatus->getType());
						$dataPointSpecification = $dataPointStatus->getValues();
					}

					if ($dataPointCode === null) {
						continue;
					}

					if ($dataPointType === 'boolean') {
						$dataPointDataType = Metadata\Types\DataTypeType::get(Metadata\Types\DataTypeType::DATA_TYPE_BOOLEAN);
					} elseif ($dataPointType === 'integer') {
						$dataPointDataType = Metadata\Types\DataTypeType::get(Metadata\Types\DataTypeType::DATA_TYPE_INT);
					} elseif ($dataPointType === 'enum') {
						$dataPointDataType = Metadata\Types\DataTypeType::get(Metadata\Types\DataTypeType::DATA_TYPE_ENUM);
					}

					try {
						$dataPointSpecification = Utils\Json::decode($dataPointSpecification, Utils\Json::FORCE_ARRAY);
						$dataPointSpecification = Utils\ArrayHash::from(is_array($dataPointSpecification) ? $dataPointSpecification : []);

					} catch (Utils\JsonException $ex) {
						$dataPointSpecification = Utils\ArrayHash::from([]);
					}

					$dataPoints[] = new Entities\Messages\DiscoveredCloudDataPointEntity(
						$device->getId(),
						$dataPointCode,
						$dataPointCode,
						$dataPointDataType,
						$dataPointSpecification->offsetExists('unit') ? strval($dataPointSpecification->offsetGet('unit')) : null,
						$dataPointSpecification->offsetExists('range') && is_array($dataPointSpecification->offsetGet('range')) ? $dataPointSpecification->offsetGet('range') : [],
						$dataPointSpecification->offsetExists('min') ? floatval($dataPointSpecification->offsetGet('min')) : null,
						$dataPointSpecification->offsetExists('max') ? floatval($dataPointSpecification->offsetGet('max')) : null,
						$dataPointSpecification->offsetExists('step') ? floatval($dataPointSpecification->offsetGet('step')) : null,
						$dataPointSpecification->offsetExists('scale') ? floatval($dataPointSpecification->offsetGet('scale')) : null,
						$dataPointStatus !== null,
						$dataPointFunction !== null,
						Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_CLOUD_DISCOVERY)
					);
				}
			} catch (Exceptions\OpenApiCallException $ex) {
				$this->logger->error(
					'Device specification could not be loaded',
					[
						'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'      => 'discovery-client',
						'device'    => [
							'id' => $device->getId(),
						],
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]
				);
			}

			$message = new Entities\Messages\DiscoveredCloudDeviceEntity(
				$device->getId(),
				$device->getLocalKey(),
				$device->getName(),
				$device->getModel(),
				$deviceFactoryInfos?->getSn(),
				$deviceFactoryInfos?->getMac(),
				$dataPoints,
				Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_CLOUD_DISCOVERY)
			);

			$this->consumer->append($message);
		}
	}

}
