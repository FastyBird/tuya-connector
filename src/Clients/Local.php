<?php declare(strict_types = 1);

/**
 * Local.php
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
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\TuyaConnector\API;
use FastyBird\TuyaConnector\Consumers;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Nette;
use Nette\Utils;
use Psr\Log;
use React\EventLoop;
use Throwable;
use function React\Async\async;

/**
 * Local devices client
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Local implements Client
{

	use Nette\SmartObject;

	private const HANDLER_START_DELAY = 2;
	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	private const SENDING_COMMAND_DELAY = 120;

	private const CMD_STATUS = 'status';

	/** @var Array<string, API\LocalApi> */
	private array $devicesClients = [];

	/** @var string[] */
	private array $processedDevices = [];

	/** @var Array<string, Array<string, DateTimeInterface|bool>> */
	private array $processedDevicesCommands = [];

	/** @var Array<string, DateTimeInterface> */
	private array $processedProperties = [];

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $handlerTimer = null;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var API\LocalApiFactory */
	private API\LocalApiFactory $localApiFactory;

	/** @var Helpers\Device */
	private Helpers\Device $deviceHelper;

	/** @var Helpers\Property */
	private Helpers\Property $propertyStateHelper;

	/** @var Consumers\Messages */
	private Consumers\Messages $consumer;

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

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Helpers\Device $deviceHelper
	 * @param Helpers\Property $propertyStateHelper
	 * @param Consumers\Messages $consumer
	 * @param API\LocalApiFactory $localApiFactory
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Helpers\Device $deviceHelper,
		Helpers\Property $propertyStateHelper,
		Consumers\Messages $consumer,
		API\LocalApiFactory $localApiFactory,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->localApiFactory = $localApiFactory;
		$this->deviceHelper = $deviceHelper;
		$this->propertyStateHelper = $propertyStateHelper;
		$this->consumer = $consumer;

		$this->devicesRepository = $devicesRepository;
		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;

		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->dateTimeFactory = $dateTimeFactory;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		$this->processedDevices = [];
		$this->processedProperties = [];

		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $deviceItem) {
			$client = $this->localApiFactory->create(
				$deviceItem->getIdentifier(),
				null,
				strval($this->deviceHelper->getConfiguration(
					$deviceItem->getId(),
					Types\DevicePropertyIdentifier::get(Types\DevicePropertyIdentifier::IDENTIFIER_LOCAL_KEY)
				)),
				strval($this->deviceHelper->getConfiguration(
					$deviceItem->getId(),
					Types\DevicePropertyIdentifier::get(Types\DevicePropertyIdentifier::IDENTIFIER_IP_ADDRESS)
				)),
				Types\DeviceProtocolVersion::get(strval($this->deviceHelper->getConfiguration(
					$deviceItem->getId(),
					Types\DevicePropertyIdentifier::get(Types\DevicePropertyIdentifier::IDENTIFIER_PROTOCOL_VERSION)
				))),
			);

			$this->devicesClients[$deviceItem->getId()->toString()] = $client;

			$client->on('message', function (Entities\API\Entity $message): void {
				var_dump('RECEIVED');
				var_dump($message->toArray());
			});

			$client->connect()
				->then(function () use ($deviceItem): void {
					$this->logger->debug(
						'Connected to device',
						[
							'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'      => 'local-client',
							'device'    => [
								'id' => $deviceItem->getId()->toString(),
							],
						]
					);
				})
				->otherwise(function (Throwable $ex) use ($deviceItem): void {
					$this->logger->error(
						'Could not establish connection with device via local protocol',
						[
							'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'      => 'local-client',
							'device'    => [
								'id' => $deviceItem->getId()->toString(),
							],
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
						]
					);
				});
		}

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
		foreach ($this->devicesClients as $client) {
			$client->disconnect();
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
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $deviceItem
	 *
	 * @return bool
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 * @throws Throwable
	 */
	private function processDevice(MetadataEntities\Modules\DevicesModule\IDeviceEntity $deviceItem): bool
	{
		if ($this->readDeviceData(self::CMD_STATUS, $deviceItem)) {
			return true;
		}

		if (
			$this->deviceConnectionStateManager->getState($deviceItem)->equalsValue(MetadataTypes\ConnectionStateType::STATE_CONNECTED)
		) {
			return $this->writeChannelsProperty($deviceItem);
		}

		return true;
	}

	/**
	 * @param string $cmd
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $deviceItem
	 *
	 * @return bool
	 *
	 * @throws Throwable
	 */
	private function readDeviceData(
		string $cmd,
		MetadataEntities\Modules\DevicesModule\IDeviceEntity $deviceItem
	): bool {
		if (!array_key_exists($deviceItem->getId()->toString(), $this->devicesClients)) {
			return false;
		}

		$cmdResult = null;

		if (!array_key_exists($deviceItem->getId()->toString(), $this->processedDevicesCommands)) {
			$this->processedDevicesCommands[$deviceItem->getId()->toString()] = [];
		}

		if (array_key_exists($cmd, $this->processedDevicesCommands[$deviceItem->getId()->toString()])) {
			$cmdResult = $this->processedDevicesCommands[$deviceItem->getId()->toString()][$cmd];
		}

		if ($cmdResult === true) {
			return false;
		}

		if (
			$cmdResult instanceof DateTimeInterface
			&& ($this->dateTimeFactory->getNow()->getTimestamp() - $cmdResult->getTimestamp()) < self::SENDING_COMMAND_DELAY
		) {
			return true;
		}

		$this->processedDevicesCommands[$deviceItem->getId()->toString()][$cmd] = $this->dateTimeFactory->getNow();

		if ($cmd === self::CMD_STATUS) {
			$this->devicesClients[$deviceItem->getId()->toString()]->readStates()
				->then(function (array $statuses) use ($cmd, $deviceItem): void {
					$this->processedDevicesCommands[$deviceItem->getId()->toString()][$cmd] = true;

					$dataPointsStatuses = [];

					foreach ($statuses as $status) {
						$dataPointsStatuses[] = new Entities\Messages\DataPointStatus(
							Types\MessageSource::get(Types\MessageSource::SOURCE_LOCAL_API),
							$status->getCode(),
							$status->getValue()
						);
					}

					$this->consumer->append(new Entities\Messages\DeviceStatus(
						Types\MessageSource::get(Types\MessageSource::SOURCE_LOCAL_API),
						$this->connector->getId(),
						$deviceItem->getIdentifier(),
						$dataPointsStatuses
					));
				})
				->otherwise(function (Throwable $ex): void {
					$this->logger->error(
						'Could not call local api',
						[
							'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'      => 'local-client',
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
						'Could not call local api',
						$ex->getCode(),
						$ex
					);
				});
		}

		return true;
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $deviceItem
	 *
	 * @return bool
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 * @throws Throwable
	 */
	private function writeChannelsProperty(MetadataEntities\Modules\DevicesModule\IDeviceEntity $deviceItem): bool
	{
		if (!array_key_exists($deviceItem->getId()->toString(), $this->devicesClients)) {
			return false;
		}

		$now = $this->dateTimeFactory->getNow();

		foreach ($this->channelsRepository->findAllByDevice($deviceItem->getId()) as $channelItem) {
			foreach ($this->channelPropertiesRepository->findAllByChannel($channelItem->getId(), MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity::class) as $propertyItem) {
				if (
					$propertyItem->isSettable()
					&& $propertyItem->getExpectedValue() !== null
					&& $propertyItem->isPending()
				) {
					$pending = is_string($propertyItem->getPending()) ? Utils\DateTime::createFromFormat(DateTimeInterface::ATOM, $propertyItem->getPending()) : true;
					$debounce = array_key_exists($propertyItem->getId()->toString(), $this->processedProperties) ? $this->processedProperties[$propertyItem->getId()->toString()] : false;

					if (
						$debounce !== false
						&& (float) $now->format('Uv') - (float) $debounce->format('Uv') < 500
					) {
						continue;
					}

					unset($this->processedProperties[$propertyItem->getId()->toString()]);

					if (
						$pending === true
						|| (
							$pending !== false
							&& (float) $now->format('Uv') - (float) $pending->format('Uv') > 2000
						)
					) {
						$this->processedProperties[$propertyItem->getId()->toString()] = $now;

						$this->devicesClients[$deviceItem->getId()->toString()]->writeState(
							$propertyItem->getIdentifier(),
							$propertyItem->getExpectedValue()
						)
							->then(function () use ($propertyItem): void {
								$this->propertyStateHelper->setValue(
									$propertyItem,
									Utils\ArrayHash::from([
										'pending' => $this->dateTimeFactory->getNow()->format(DateTimeInterface::ATOM),
									])
								);
							})
							->otherwise(function (Throwable $ex) use ($propertyItem): void {
								$this->logger->error(
									'Could not call local device api',
									[
										'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
										'type'      => 'local-client',
										'connector' => [
											'id' => $this->connector->getId()->toString(),
										],
										'exception' => [
											'message' => $ex->getMessage(),
											'code'    => $ex->getCode(),
										],
									]
								);

								$this->propertyStateHelper->setValue(
									$propertyItem,
									Utils\ArrayHash::from([
										'expectedValue' => null,
										'pending'       => false,
									])
								);

								unset($this->processedProperties[$propertyItem->getId()->toString()]);

								throw new DevicesModuleExceptions\TerminateException(
									'Could not call local api',
									$ex->getCode(),
									$ex
								);
							});

						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * @return void
	 */
	private function registerLoopHandler(): void
	{
		$this->handlerTimer = $this->eventLoop->addTimer(
			self::HANDLER_PROCESSING_INTERVAL,
			async(function (): void {
				$this->handleCommunication();
			})
		);
	}

}
