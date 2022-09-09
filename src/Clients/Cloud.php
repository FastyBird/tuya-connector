<?php declare(strict_types = 1);

/**
 * Cloud.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 * @since          0.13.0
 *
 * @date           25.08.22
 */

namespace FastyBird\TuyaConnector\Clients;

use DateTimeInterface;
use FastyBird\DateTimeFactory;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Metadata\Schemas as MetadataSchemas;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\TuyaConnector;
use FastyBird\TuyaConnector\API;
use FastyBird\TuyaConnector\Consumers;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Exceptions;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Nette;
use Nette\Utils;
use Psr\Log;
use Ratchet;
use Ratchet\RFC6455;
use React\Async;
use React\EventLoop;
use React\Http as ReactHttp;
use React\Socket;
use Throwable;

/**
 * Cloud devices client
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Cloud implements Client
{

	use Nette\SmartObject;

	private const PING_INTERVAL = 30;

	private const HANDLER_START_DELAY = 2;
	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	private const SENDING_READ_STATUS_DELAY = 120;

	public const WS_MESSAGE_SCHEMA_FILENAME = 'openpulsar_message.json';
	public const WS_MESSAGE_PAYLOAD_SCHEMA_FILENAME = 'openpulsar_payload.json';
	public const WS_MESSAGE_PAYLOAD_DATA_SCHEMA_FILENAME = 'openpulsar_data.json';

	private const CMD_INFO = 'info';
	private const CMD_STATUS = 'status';

	/** @var string[] */
	private array $processedDevices = [];

	/** @var Array<string, Array<string, DateTimeInterface|bool>> */
	private array $processedDevicesCommands = [];

	/** @var Array<string, DateTimeInterface> */
	private array $processedProperties = [];

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $pingTimer = null;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $handlerTimer = null;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Ratchet\Client\WebSocket|null */
	private ?Ratchet\Client\WebSocket $wsConnection = null;

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

	/** @var Helpers\Property */
	private Helpers\Property $propertyStateHelper;

	/** @var Consumers\Messages */
	private Consumers\Messages $consumer;

	/** @var API\OpenApi */
	private API\OpenApi $openApiApi;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelPropertiesRepository */
	private DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository;

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var MetadataSchemas\IValidator */
	private MetadataSchemas\IValidator $schemaValidator;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Helpers\Connector $connectorHelper
	 * @param Helpers\Property $propertyStateHelper
	 * @param Consumers\Messages $consumer
	 * @param API\OpenApiFactory $openApiApiFactory
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param MetadataSchemas\IValidator $schemaValidator
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Helpers\Connector $connectorHelper,
		Helpers\Property $propertyStateHelper,
		Consumers\Messages $consumer,
		API\OpenApiFactory $openApiApiFactory,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		MetadataSchemas\IValidator $schemaValidator,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->connectorHelper = $connectorHelper;
		$this->propertyStateHelper = $propertyStateHelper;

		$this->consumer = $consumer;

		$this->devicesRepository = $devicesRepository;

		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;

		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->dateTimeFactory = $dateTimeFactory;
		$this->schemaValidator = $schemaValidator;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();

		$this->openApiApi = $openApiApiFactory->create($this->connector);
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		$this->processedDevices = [];
		$this->processedDevicesCommands = [];
		$this->processedProperties = [];

		$this->pingTimer = null;
		$this->handlerTimer = null;

		$reactConnector = new Socket\Connector([
			'dns'     => '8.8.8.8',
			'timeout' => 10,
			'tls'     => [
				'verify_peer'      => false,
				'verify_peer_name' => false,
				'check_hostname'   => false,
			],
		]);

		$connector = new Ratchet\Client\Connector($this->eventLoop, $reactConnector);
		$connector(
			$this->buildWsTopicUrl(),
			[],
			[
				'Connection' => 'Upgrade',
				'username'   => $this->connectorHelper->getConfiguration(
					$this->connector->getId(),
					Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
				),
				'password'   => $this->genPwd(),
			],
		)
			->then(function (Ratchet\Client\WebSocket $connection): void {
				$this->logger->debug(
					'Connected to Tuya sockets server',
					[
						'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'   => 'cloud-client',
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
					]
				);

				$this->wsConnection = $connection;

				$connection->on('message', function (RFC6455\Messaging\MessageInterface $message): void {
					$this->handleWsMessage($message->getPayload());
				});

				$connection->on('close', function ($code = null, $reason = null) {
					$this->logger->debug(
						'Connection to Tuya WS server was closed',
						[
							'source'     => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'       => 'cloud-client',
							'connection' => [
								'code'   => $code,
								'reason' => $reason,
							],
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
						]
					);

					if ($this->pingTimer !== null) {
						$this->eventLoop->cancelTimer($this->pingTimer);

						$this->pingTimer = null;
					}

					$this->wsConnection = null;
				});

				$connection->on('error', function (Throwable $ex): void {
					$this->logger->error(
						'An error occurred on WS connection',
						[
							'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'      => 'cloud-client',
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
						]
					);

					throw new DevicesModuleExceptions\TerminateException('Connection to WS server was terminated');
				});

				$this->pingTimer = $this->eventLoop->addPeriodicTimer(
					self::PING_INTERVAL,
					function () use ($connection): void {
						$connection->send(new RFC6455\Messaging\Frame(
							strval($this->connectorHelper->getConfiguration(
								$this->connector->getId(),
								Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
							)),
							true,
							RFC6455\Messaging\Frame::OP_PING
						));
					}
				);
			})
			->otherwise(function (Throwable $ex): void {
				$this->logger->error(
					'Connection to Tuya WS server failed',
					[
						'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'      => 'cloud-client',
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]
				);

				throw new DevicesModuleExceptions\TerminateException(
					'Connection to Tuya WS server failed',
					$ex->getCode(),
					$ex
				);
			});

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->registerLoopHandler();
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		$this->wsConnection?->close();

		if ($this->pingTimer !== null) {
			$this->eventLoop->cancelTimer($this->pingTimer);

			$this->pingTimer = null;
		}

		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);

			$this->handlerTimer = null;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

	/**
	 * @return void
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 * @throws Throwable
	 */
	private function handleCommunication(): void
	{
		if (!$this->openApiApi->isConnected()) {
			Async\await($this->openApiApi->connect());
		}

		foreach ($this->processedProperties as $index => $processedProperty) {
			if (((float) $this->dateTimeFactory->getNow()->format('Uv') - (float) $processedProperty->format('Uv')) >= 500) {
				unset($this->processedProperties[$index]);
			}
		}

		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			if (
				!in_array($device->getId()->toString(), $this->processedDevices, true)
				&& !$this->deviceConnectionStateManager->getState($device)->equalsValue(MetadataTypes\ConnectionStateType::STATE_STOPPED)
			) {
				$this->processedDevices[] = $device->getId()->toString();

				if ($this->processDevice($device)) {
					$this->registerLoopHandler();

					return;
				}
			}
		}

		$this->processedDevices = [];

		$this->registerLoopHandler();
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return bool
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 * @throws Throwable
	 */
	private function processDevice(MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): bool
	{
		if ($this->readDeviceData(self::CMD_INFO, $device)) {
			return true;
		}

		if ($this->readDeviceData(self::CMD_STATUS, $device)) {
			return true;
		}

		if (
			$this->deviceConnectionStateManager->getState($device)->equalsValue(MetadataTypes\ConnectionStateType::STATE_CONNECTED)
		) {
			return $this->writeChannelsProperty($device);
		}

		return true;
	}

	/**
	 * @param string $cmd
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return bool
	 *
	 * @throws Throwable
	 */
	private function readDeviceData(string $cmd, MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): bool
	{
		$httpCmdResult = null;

		if (!array_key_exists($device->getId()->toString(), $this->processedDevicesCommands)) {
			$this->processedDevicesCommands[$device->getId()->toString()] = [];
		}

		if (array_key_exists($cmd, $this->processedDevicesCommands[$device->getId()->toString()])) {
			$httpCmdResult = $this->processedDevicesCommands[$device->getId()->toString()][$cmd];
		}

		if ($httpCmdResult === true) {
			return false;
		}

		if (
			$httpCmdResult instanceof DateTimeInterface
			&& ($this->dateTimeFactory->getNow()->getTimestamp() - $httpCmdResult->getTimestamp()) < self::SENDING_READ_STATUS_DELAY
		) {
			return true;
		}

		$this->processedDevicesCommands[$device->getId()->toString()][$cmd] = $this->dateTimeFactory->getNow();

		if ($cmd === self::CMD_INFO) {
			if ($this->deviceConnectionStateManager->getState($device)->equalsValue(MetadataTypes\ConnectionStateType::STATE_CONNECTED)) {
				$this->processedDevicesCommands[$device->getId()->toString()][$cmd] = true;

				return false;
			}

			$this->openApiApi->getDeviceInformation($device->getIdentifier())
				->then(function (Entities\API\DeviceInformation $deviceInformation) use ($cmd, $device): void {
					if ($deviceInformation->isOnline()) {
						$this->processedDevicesCommands[$device->getId()->toString()][$cmd] = true;
					}

					$this->consumer->append(new Entities\Messages\DeviceState(
						Types\MessageSource::get(Types\MessageSource::SOURCE_CLOUD_OPENAPI),
						$this->connector->getId(),
						$device->getIdentifier(),
						$deviceInformation->isOnline()
					));
				})
				->otherwise(function (Throwable $ex): void {
					$this->logger->error(
						'Could not call cloud openapi',
						[
							'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'      => 'cloud-client',
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
						]
					);

					throw new DevicesModuleExceptions\TerminateException(
						'Could not call cloud openapi',
						$ex->getCode(),
						$ex
					);
				});

		} elseif ($cmd === self::CMD_STATUS) {
			$this->openApiApi->getDeviceStatus($device->getIdentifier())
				->then(function (array $statuses) use ($cmd, $device): void {
					$this->processedDevicesCommands[$device->getId()->toString()][$cmd] = true;

					$dataPointsStatuses = [];

					foreach ($statuses as $status) {
						$dataPointsStatuses[] = new Entities\Messages\DataPointStatus(
							Types\MessageSource::get(Types\MessageSource::SOURCE_CLOUD_OPENAPI),
							$status->getCode(),
							$status->getValue()
						);
					}

					$this->consumer->append(new Entities\Messages\DeviceStatus(
						Types\MessageSource::get(Types\MessageSource::SOURCE_CLOUD_OPENAPI),
						$this->connector->getId(),
						$device->getIdentifier(),
						$dataPointsStatuses
					));
				})
				->otherwise(function (Throwable $ex): void {
					$this->logger->error(
						'Could not call cloud openapi',
						[
							'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'      => 'cloud-client',
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
						]
					);

					throw new DevicesModuleExceptions\TerminateException(
						'Could not call cloud openapi',
						$ex->getCode(),
						$ex
					);
				});
		}

		return true;
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return bool
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 * @throws Throwable
	 */
	private function writeChannelsProperty(MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): bool
	{
		$now = $this->dateTimeFactory->getNow();

		foreach ($this->channelsRepository->findAllByDevice($device->getId()) as $channel) {
			foreach ($this->channelPropertiesRepository->findAllByChannel($channel->getId(), MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity::class) as $property) {
				if (
					$property->isSettable()
					&& $property->getExpectedValue() !== null
					&& $property->isPending()
				) {
					$pending = is_string($property->getPending()) ? Utils\DateTime::createFromFormat(DateTimeInterface::ATOM, $property->getPending()) : true;
					$debounce = array_key_exists($property->getId()->toString(), $this->processedProperties) ? $this->processedProperties[$property->getId()->toString()] : false;

					if (
						$debounce !== false
						&& (float) $now->format('Uv') - (float) $debounce->format('Uv') < 500
					) {
						continue;
					}

					unset($this->processedProperties[$property->getId()->toString()]);

					if (
						$pending === true
						|| (
							$pending !== false
							&& (float) $now->format('Uv') - (float) $pending->format('Uv') > 2000
						)
					) {
						$this->processedProperties[$property->getId()->toString()] = $now;

						$this->openApiApi->setDeviceStatus(
							$device->getIdentifier(),
							$property->getIdentifier(),
							$property->getExpectedValue()
						)
							->then(function () use ($property): void {
								$this->propertyStateHelper->setValue(
									$property,
									Utils\ArrayHash::from([
										'pending' => $this->dateTimeFactory->getNow()->format(DateTimeInterface::ATOM),
									])
								);
							})
							->otherwise(function (Throwable $ex) use ($device, $channel, $property): void {
								if ($ex instanceof ReactHttp\Message\ResponseException) {
									if ($ex->getCode() >= 400 && $ex->getCode() < 499) {
										$this->propertyStateHelper->setValue(
											$property,
											Utils\ArrayHash::from([
												'expectedValue' => null,
												'pending'       => false,
											])
										);

										$this->logger->warning(
											'Expected value could not be set',
											[
												'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
												'type'      => 'cloud-client',
												'exception' => [
													'message' => $ex->getMessage(),
													'code'    => $ex->getCode(),
												],
												'connector' => [
													'id' => $this->connector->getId()->toString(),
												],
												'device'    => [
													'id' => $device->getId()->toString(),
												],
												'channel'   => [
													'id' => $channel->getId()->toString(),
												],
												'property'  => [
													'id' => $property->getId()->toString(),
												],
											]
										);

									} elseif ($ex->getCode() >= 500 && $ex->getCode() < 599) {
										$this->deviceConnectionStateManager->setState(
											$device,
											MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_LOST)
										);
									}
								}

								unset($this->processedProperties[$property->getId()->toString()]);
							});

						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	private function handleWsMessage(string $message): void
	{
		try {
			$message = $this->schemaValidator->validate(
				$message,
				$this->getSchemaFilePath(self::WS_MESSAGE_SCHEMA_FILENAME)
			);

		} catch (MetadataExceptions\LogicException | MetadataExceptions\MalformedInputException | MetadataExceptions\InvalidDataException | Exceptions\OpenPulsarHandle $ex) {
			$this->logger->error(
				'Could not decode received WS message',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'      => 'cloud-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
					'data'      => [
						'message' => $message,
					],
				]
			);

			return;
		}

		if ($this->wsConnection !== null && $message->offsetExists('messageId')) {
			try {
				// Confirm received message
				// Received message have to confirmed to be removed from queue on Tuya server side
				$this->wsConnection->send(Utils\Json::encode(['messageId' => $message->offsetGet('messageId')]));

			} catch (Utils\JsonException $ex) {
				$this->logger->error(
					'Could not confirm received WS message',
					[
						'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'      => 'cloud-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
						'data'      => [
							'message' => $message,
						],
					]
				);
			}
		}

		if (!$message->offsetExists('payload')) {
			$this->logger->error(
				'Received WS message is invalid',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'cloud-client',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);

			return;
		}

		$payload = base64_decode(strval($message->offsetGet('payload')), true);

		if ($payload === false) {
			$this->logger->error(
				'Received WS message payload could not be decoded',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'cloud-client',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);

			return;
		}

		$this->logger->debug(
			'Received message origin payload',
			[
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'cloud-client',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
				'data'   => [
					'payload' => $payload,
				],
			]
		);

		try {
			$payload = $this->schemaValidator->validate(
				$payload,
				$this->getSchemaFilePath(self::WS_MESSAGE_PAYLOAD_SCHEMA_FILENAME)
			);

		} catch (MetadataExceptions\LogicException | MetadataExceptions\MalformedInputException | MetadataExceptions\InvalidDataException | Exceptions\OpenPulsarHandle $ex) {
			$this->logger->error(
				'Could not decode received WS message payload',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'      => 'cloud-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
					'data'      => [
						'payload' => $payload,
					],
				]
			);

			return;
		}

		if (!$payload->offsetExists('data')) {
			$this->logger->error(
				'Received WS message payload is invalid',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'cloud-client',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);

			return;
		}

		$data = base64_decode(strval($payload->offsetGet('data')), true);

		if ($data === false) {
			$this->logger->error(
				'Received WS message payload data could not be decoded',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'cloud-client',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);

			return;
		}

		$accessSecret = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET)
		);

		$decodingKey = Utils\Strings::substring(strval($accessSecret), 8, 16);

		$decryptedData = openssl_decrypt(
			$data,
			'AES-128-ECB',
			$decodingKey,
			OPENSSL_RAW_DATA
		);

		if ($decryptedData === false) {
			$this->logger->error(
				'Received WS message payload data could not be decrypted',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'cloud-client',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);

			return;
		}

		$this->logger->debug(
			'Received message decrypted',
			[
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'cloud-client',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
				'data'   => $decryptedData,
			]
		);

		try {
			$decryptedData = $this->schemaValidator->validate(
				$decryptedData,
				$this->getSchemaFilePath(self::WS_MESSAGE_PAYLOAD_DATA_SCHEMA_FILENAME)
			);

		} catch (MetadataExceptions\LogicException | MetadataExceptions\MalformedInputException | MetadataExceptions\InvalidDataException | Exceptions\OpenPulsarHandle $ex) {
			$this->logger->error(
				'Could not decode received WS message payload data decrypted',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'      => 'cloud-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
					'data'      => [
						'data' => $decryptedData,
					],
				]
			);

			return;
		}

		if (
			$decryptedData->offsetExists('devId')
			&& $decryptedData->offsetExists('status')
			&& $decryptedData->offsetGet('status') instanceof Utils\ArrayHash
		) {
			$dataPointsStatuses = [];

			foreach ($decryptedData->status as $status) {
				if (
					!$status instanceof Utils\ArrayHash
					|| !$status->offsetExists('code')
					|| !$status->offsetExists('value')
				) {
					continue;
				}

				if (is_string($status->value) || is_bool($status->value) || is_numeric($status->value)) {
					$dataPointsStatuses[] = new Entities\Messages\DataPointStatus(
						Types\MessageSource::get(Types\MessageSource::SOURCE_CLOUD_OPENPULSAR),
						$status->code,
						$status->value
					);
				}
			}

			$this->consumer->append(new Entities\Messages\DeviceStatus(
				Types\MessageSource::get(Types\MessageSource::SOURCE_CLOUD_OPENPULSAR),
				$this->connector->getId(),
				$decryptedData->devId,
				$dataPointsStatuses
			));

			return;
		}

		if (
			$decryptedData->offsetExists('bizCode')
			&& (
				$decryptedData->offsetGet('bizCode') === Types\OpenPulsarMessageType::BIZ_CODE_ONLINE
				|| $decryptedData->offsetGet('bizCode') === Types\OpenPulsarMessageType::BIZ_CODE_OFFLINE
			)
		) {
			$this->consumer->append(new Entities\Messages\DeviceState(
				Types\MessageSource::get(Types\MessageSource::SOURCE_CLOUD_OPENPULSAR),
				$this->connector->getId(),
				$decryptedData->devId,
				$decryptedData->offsetGet('bizCode') === Types\OpenPulsarMessageType::BIZ_CODE_ONLINE
			));
		}
	}

	/**
	 * @return string
	 */
	private function buildWsTopicUrl(): string
	{
		return $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_OPENPULSAR_ENDPOINT)
		) . 'ws/v2/consumer/persistent/' . $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
		) . '/out/' . $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_OPENPULSAR_TOPIC)
		) . '/' . $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
		) . '-sub?ackTimeoutMillis=3000&subscriptionType=Failover';
	}

	/**
	 * @return string
	 */
	private function genPwd(): string
	{
		$passString = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
		) . md5(
			strval($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET)
			))
		);

		return Utils\Strings::substring(md5($passString), 8, 16);
	}

	/**
	 * @return void
	 */
	private function registerLoopHandler(): void
	{
		$this->handlerTimer = $this->eventLoop->addTimer(
			self::HANDLER_PROCESSING_INTERVAL,
			Async\async(function (): void {
				$this->handleCommunication();
			})
		);
	}

	/**
	 * @param string $schemaFilename
	 *
	 * @return string
	 */
	private function getSchemaFilePath(string $schemaFilename): string
	{
		try {
			$schema = Utils\FileSystem::read(TuyaConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . $schemaFilename);

		} catch (Nette\IOException) {
			throw new Exceptions\OpenPulsarHandle('Validation schema for response could not be loaded');
		}

		return $schema;
	}

}
