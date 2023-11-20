<?php declare(strict_types = 1);

/**
 * WriteChannelPropertyState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Queue
 * @since          1.0.0
 *
 * @date           17.08.23
 */

namespace FastyBird\Connector\Tuya\Queue\Consumers;

use DateTimeInterface;
use FastyBird\Connector\Tuya;
use FastyBird\Connector\Tuya\API;
use FastyBird\Connector\Tuya\Entities;
use FastyBird\Connector\Tuya\Exceptions;
use FastyBird\Connector\Tuya\Helpers;
use FastyBird\Connector\Tuya\Queries;
use FastyBird\Connector\Tuya\Queue;
use FastyBird\Connector\Tuya\Types;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\States as DevicesStates;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Nette\Utils;
use RuntimeException;
use Throwable;
use function array_merge;
use function strval;

/**
 * Write state to device message consumer
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class WriteChannelPropertyState implements Queue\Consumer
{

	use Nette\SmartObject;

	public function __construct(
		private readonly Queue\Queue $queue,
		private readonly API\ConnectionManager $connectionManager,
		private readonly Helpers\Entity $entityHelper,
		private readonly Tuya\Logger $logger,
		private readonly DevicesModels\Entities\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Entities\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStatesManager,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\WriteChannelPropertyState) {
			return false;
		}

		$findConnectorQuery = new Queries\Entities\FindConnectors();
		$findConnectorQuery->byId($entity->getConnector());

		$connector = $this->connectorsRepository->findOneBy($findConnectorQuery, Entities\TuyaConnector::class);

		if ($connector === null) {
			$this->logger->error(
				'Connector could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$findDeviceQuery = new Queries\Entities\FindDevices();
		$findDeviceQuery->forConnector($connector);
		$findDeviceQuery->byId($entity->getDevice());

		$device = $this->devicesRepository->findOneBy($findDeviceQuery, Entities\TuyaDevice::class);

		if ($device === null) {
			$this->logger->error(
				'Device could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $connector->getId()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$findChannelQuery = new DevicesQueries\Entities\FindChannels();
		$findChannelQuery->forDevice($device);
		$findChannelQuery->byId($entity->getChannel());

		$channel = $this->channelsRepository->findOneBy($findChannelQuery);

		if ($channel === null) {
			$this->logger->error(
				'Channel could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $connector->getId()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelDynamicProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byId($entity->getProperty());

		$property = $this->channelsPropertiesRepository->findOneBy(
			$findChannelPropertyQuery,
			DevicesEntities\Channels\Properties\Dynamic::class,
		);

		if ($property === null) {
			$this->logger->error(
				'Channel property could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $connector->getId()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $channel->getId()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		if (!$property->isSettable()) {
			$this->logger->error(
				'Channel property is not writable',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $connector->getId()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $channel->getId()->toString(),
					],
					'property' => [
						'id' => $property->getId()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$state = $this->channelPropertiesStatesManager->getValue($property);

		if ($state === null) {
			return true;
		}

		$expectedValue = DevicesUtilities\ValueHelper::flattenValue($state->getExpectedValue());

		if ($expectedValue === null) {
			return true;
		}

		try {
			if ($connector->getClientMode()->equalsValue(Types\ClientMode::CLOUD)) {
				$client = $this->connectionManager->getCloudApiConnection($connector);

				if (!$client->isConnected()) {
					$client->connect();
				}

				$result = $client->setDeviceState(
					$device->getIdentifier(),
					$property->getIdentifier(),
					$expectedValue,
				);
			} elseif ($connector->getClientMode()->equalsValue(Types\ClientMode::LOCAL)) {
				$client = $this->connectionManager->getLocalConnection($device);

				$result = $client->writeState(
					$property->getIdentifier(),
					$expectedValue,
					$device->getGateway() !== null ? $device->getIdentifier() : null,
				);
			} else {
				return true;
			}
		} catch (Exceptions\InvalidState $ex) {
			$this->queue->append(
				$this->entityHelper->create(
					Entities\Messages\StoreDeviceConnectionState::class,
					[
						'connector' => $connector->getId()->toString(),
						'device' => $device->getId()->toString(),
						'state' => MetadataTypes\ConnectionState::STATE_ALERT,
					],
				),
			);

			$this->logger->error(
				'Device is not properly configured',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'write-channel-property-state-message-consumer',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
					'connector' => [
						'id' => $connector->getId()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $channel->getId()->toString(),
					],
					'property' => [
						'id' => $property->getId()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		} catch (Exceptions\OpenApiError | Exceptions\LocalApiError $ex) {
			$this->queue->append(
				$this->entityHelper->create(
					Entities\Messages\StoreDeviceConnectionState::class,
					[
						'connector' => $connector->getId()->toString(),
						'device' => $device->getId()->toString(),
						'state' => MetadataTypes\ConnectionState::STATE_ALERT,
					],
				),
			);

			$this->logger->error(
				'Preparing api request failed',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'write-channel-property-state-message-consumer',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
					'connector' => [
						'id' => $connector->getId()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $channel->getId()->toString(),
					],
					'property' => [
						'id' => $property->getId()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		} catch (Exceptions\OpenApiCall | Exceptions\LocalApiCall $ex) {
			$this->queue->append(
				$this->entityHelper->create(
					Entities\Messages\StoreDeviceConnectionState::class,
					[
						'connector' => $connector->getId()->toString(),
						'device' => $device->getId()->toString(),
						'state' => MetadataTypes\ConnectionState::STATE_DISCONNECTED,
					],
				),
			);

			$extra = [];

			if ($ex instanceof Exceptions\OpenApiCall) {
				$extra = [
					'request' => [
						'method' => $ex->getRequest()?->getMethod(),
						'url' => $ex->getRequest() !== null ? strval($ex->getRequest()->getUri()) : null,
						'body' => $ex->getRequest()?->getBody()->getContents(),
					],
					'response' => [
						'body' => $ex->getResponse()?->getBody()->getContents(),
					],
				];
			}

			$this->logger->error(
				'Calling device api failed',
				array_merge(
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
						'type' => 'write-channel-property-state-message-consumer',
						'exception' => BootstrapHelpers\Logger::buildException($ex),
						'connector' => [
							'id' => $connector->getId()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
						'property' => [
							'id' => $property->getId()->toString(),
						],
						'data' => $entity->toArray(),
					],
					$extra,
				),
			);

			return true;
		}

		$result->then(
			function () use ($property): void {
				$now = $this->dateTimeFactory->getNow();

				$state = $this->channelPropertiesStatesManager->getValue($property);

				if ($state?->getExpectedValue() !== null) {
					$this->channelPropertiesStatesManager->setValue(
						$property,
						Utils\ArrayHash::from([
							DevicesStates\Property::PENDING_FIELD => $now->format(DateTimeInterface::ATOM),
						]),
					);
				}
			},
			function (Throwable $ex) use ($connector, $device, $channel, $property, $entity): void {
				$this->channelPropertiesStatesManager->setValue(
					$property,
					Utils\ArrayHash::from([
						DevicesStates\Property::EXPECTED_VALUE_FIELD => null,
						DevicesStates\Property::PENDING_FIELD => false,
					]),
				);

				$extra = [];

				if ($ex instanceof Exceptions\OpenApiCall) {
					$extra = [
						'request' => [
							'method' => $ex->getRequest()?->getMethod(),
							'url' => $ex->getRequest() !== null ? strval($ex->getRequest()->getUri()) : null,
							'body' => $ex->getRequest()?->getBody()->getContents(),
						],
						'response' => [
							'body' => $ex->getResponse()?->getBody()->getContents(),
						],
					];
				}

				if ($ex instanceof Exceptions\OpenApiCall || $ex instanceof Exceptions\LocalApiCall) {
					$this->queue->append(
						$this->entityHelper->create(
							Entities\Messages\StoreDeviceConnectionState::class,
							[
								'connector' => $connector->getId()->toString(),
								'identifier' => $device->getIdentifier(),
								'state' => MetadataTypes\ConnectionState::STATE_DISCONNECTED,
							],
						),
					);

				} elseif ($ex instanceof Exceptions\OpenApiError || $ex instanceof Exceptions\LocalApiError) {
					$this->queue->append(
						$this->entityHelper->create(
							Entities\Messages\StoreDeviceConnectionState::class,
							[
								'connector' => $connector->getId()->toString(),
								'identifier' => $device->getIdentifier(),
								'state' => MetadataTypes\ConnectionState::STATE_ALERT,
							],
						),
					);

				} else {
					$this->queue->append(
						$this->entityHelper->create(
							Entities\Messages\StoreDeviceConnectionState::class,
							[
								'connector' => $connector->getId()->toString(),
								'identifier' => $device->getIdentifier(),
								'state' => MetadataTypes\ConnectionState::STATE_LOST,
							],
						),
					);
				}

				$this->logger->error(
					'Could write state to device',
					array_merge(
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
							'type' => 'write-channel-property-state-message-consumer',
							'exception' => BootstrapHelpers\Logger::buildException($ex),
							'connector' => [
								'id' => $connector->getId()->toString(),
							],
							'device' => [
								'id' => $device->getId()->toString(),
							],
							'channel' => [
								'id' => $channel->getId()->toString(),
							],
							'property' => [
								'id' => $property->getId()->toString(),
							],
							'data' => $entity->toArray(),
						],
						$extra,
					),
				);
			},
		);

		$this->logger->debug(
			'Consumed write sub device state message',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
				'type' => 'write-channel-property-state-message-consumer',
				'connector' => [
					'id' => $connector->getId()->toString(),
				],
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'channel' => [
					'id' => $channel->getId()->toString(),
				],
				'property' => [
					'id' => $property->getId()->toString(),
				],
				'data' => $entity->toArray(),
			],
		);

		return true;
	}

}
