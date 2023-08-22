<?php declare(strict_types = 1);

/**
 * StoreLocalDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Queue
 * @since          1.0.0
 *
 * @date           24.08.22
 */

namespace FastyBird\Connector\Tuya\Queue\Consumers;

use Doctrine\DBAL;
use FastyBird\Connector\Tuya;
use FastyBird\Connector\Tuya\Entities;
use FastyBird\Connector\Tuya\Helpers;
use FastyBird\Connector\Tuya\Queries;
use FastyBird\Connector\Tuya\Queue;
use FastyBird\Connector\Tuya\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Nette\Utils;
use function assert;
use function count;

/**
 * Local device discovery message consumer
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class StoreLocalDevice implements Queue\Consumer
{

	use Nette\SmartObject;
	use DeviceProperty;

	public function __construct(
		protected readonly Tuya\Logger $logger,
		protected readonly DevicesModels\Devices\DevicesRepository $devicesRepository,
		protected readonly DevicesModels\Devices\Properties\PropertiesRepository $devicesPropertiesRepository,
		protected readonly DevicesModels\Devices\Properties\PropertiesManager $devicesPropertiesManager,
		protected readonly DevicesUtilities\Database $databaseHelper,
		private readonly DevicesModels\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesModels\Channels\Properties\PropertiesManager $channelsPropertiesManager,
		private readonly DevicesModels\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Devices\DevicesManager $devicesManager,
		private readonly DevicesModels\Channels\ChannelsManager $channelsManager,
	)
	{
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\StoreLocalDevice) {
			return false;
		}

		$findDeviceQuery = new Queries\FindDevices();
		$findDeviceQuery->byConnectorId($entity->getConnector());
		$findDeviceQuery->byIdentifier($entity->getId());

		$device = $this->devicesRepository->findOneBy($findDeviceQuery, Entities\TuyaDevice::class);

		if ($device === null) {
			$findConnectorQuery = new Queries\FindConnectors();
			$findConnectorQuery->byId($entity->getConnector());

			$connector = $this->connectorsRepository->findOneBy(
				$findConnectorQuery,
				Entities\TuyaConnector::class,
			);

			if ($connector === null) {
				return true;
			}

			$device = $this->databaseHelper->transaction(
				function () use ($entity, $connector): Entities\TuyaDevice {
					$device = $this->devicesManager->create(Utils\ArrayHash::from([
						'entity' => Entities\TuyaDevice::class,
						'connector' => $connector,
						'identifier' => $entity->getId(),
						'name' => $entity->getName(),
					]));
					assert($device instanceof Entities\TuyaDevice);

					return $device;
				},
			);

			$this->logger->debug(
				'Device was created',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'store-local-device-message-consumer',
					'device' => [
						'id' => $device->getId()->toString(),
						'identifier' => $entity->getId(),
						'address' => $entity->getIpAddress(),
					],
					'data' => $entity->toArray(),
				],
			);
		} else {
			$device = $this->databaseHelper->transaction(
				function () use ($entity, $device): Entities\TuyaDevice {
					$device = $this->devicesManager->update($device, Utils\ArrayHash::from([
						'name' => $entity->getName(),
					]));
					assert($device instanceof Entities\TuyaDevice);

					return $device;
				},
			);

			$this->logger->debug(
				'Device was updated',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'store-local-device-message-consumer',
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);
		}

		if ($entity->getGateway() !== null) {
			$findParentDeviceQuery = new Queries\FindDevices();
			$findParentDeviceQuery->byConnectorId($entity->getConnector());
			$findParentDeviceQuery->byIdentifier($entity->getGateway());

			$parent = $this->devicesRepository->findOneBy($findParentDeviceQuery, Entities\TuyaDevice::class);

			if ($parent === null) {
				$this->databaseHelper->transaction(
					function () use ($device): void {
						$this->devicesManager->delete($device);
					},
				);

				return true;
			}

			$this->databaseHelper->transaction(
				function () use ($device, $parent): void {
					$this->devicesManager->update($device, Utils\ArrayHash::from([
						'parents' => [$parent],
					]));
				},
			);
		}

		$this->setDeviceProperty(
			$device->getId(),
			$entity->getIpAddress(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::IP_ADDRESS,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::IP_ADDRESS),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getVersion(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_ENUM),
			Types\DevicePropertyIdentifier::PROTOCOL_VERSION,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::PROTOCOL_VERSION),
			[
				Types\DeviceProtocolVersion::V31,
				Types\DeviceProtocolVersion::V32,
				Types\DeviceProtocolVersion::V33,
				Types\DeviceProtocolVersion::V34,
				Types\DeviceProtocolVersion::V32_PLUS,
			],
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getLocalKey(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::LOCAL_KEY,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::LOCAL_KEY),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getNodeId(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::NODE_ID,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::NODE_ID),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getGateway(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::GATEWAY_ID,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::GATEWAY_ID),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getCategory(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::CATEGORY,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::CATEGORY),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getIcon(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::ICON,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::ICON),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getLatitude(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::LATITUDE,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::LATITUDE),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getLongitude(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::LONGITUDE,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::LONGITUDE),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getProductId(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::PRODUCT_ID,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::PRODUCT_ID),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getProductName(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::PRODUCT_NAME,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::PRODUCT_NAME),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->isEncrypted(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_BOOLEAN),
			Types\DevicePropertyIdentifier::ENCRYPTED,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::ENCRYPTED),
		);

		$this->setDeviceProperty(
			$device->getId(),
			$entity->getModel(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::MODEL,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::MODEL),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getMac(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::MAC_ADDRESS,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::MAC_ADDRESS),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getSn(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::SERIAL_NUMBER,
			Helpers\Name::createName(Types\DevicePropertyIdentifier::SERIAL_NUMBER),
		);

		if (count($entity->getDataPoints()) > 0) {
			$this->databaseHelper->transaction(function () use ($entity, $device): bool {
				$findChannelQuery = new DevicesQueries\FindChannels();
				$findChannelQuery->byIdentifier(Types\DataPoint::LOCAL);
				$findChannelQuery->forDevice($device);

				$channel = $this->channelsRepository->findOneBy($findChannelQuery);

				if ($channel === null) {
					$channel = $this->channelsManager->create(Utils\ArrayHash::from([
						'device' => $device,
						'identifier' => Types\DataPoint::LOCAL,
					]));

					$this->logger->debug(
						'Device channel was created',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
							'type' => 'store-local-device-message-consumer',
							'device' => [
								'id' => $device->getPlainId(),
							],
							'channel' => [
								'id' => $channel->getPlainId(),
							],
						],
					);
				}

				foreach ($entity->getDataPoints() as $dataPoint) {
					$findChannelPropertyQuery = new DevicesQueries\FindChannelDynamicProperties();
					$findChannelPropertyQuery->forChannel($channel);
					$findChannelPropertyQuery->byIdentifier($dataPoint->getCode());

					$property = $this->channelsPropertiesRepository->findOneBy(
						$findChannelPropertyQuery,
						DevicesEntities\Channels\Properties\Dynamic::class,
					);

					if ($property === null) {
						$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
							'channel' => $channel,
							'entity' => DevicesEntities\Channels\Properties\Dynamic::class,
							'identifier' => $dataPoint->getCode(),
							'name' => $dataPoint->getCode(),
							'dataType' => $dataPoint->getDataType(),
							'unit' => $dataPoint->getUnit(),
							'format' => $dataPoint->getFormat(),
							'queryable' => $dataPoint->isQueryable(),
							'settable' => $dataPoint->isSettable(),
						]));

					} else {
						$this->channelsPropertiesManager->update($property, Utils\ArrayHash::from([
							'name' => $property->getName() ?? $dataPoint->getCode(),
							'dataType' => $dataPoint->getDataType(),
							'unit' => $dataPoint->getUnit(),
							'format' => $dataPoint->getFormat(),
							'queryable' => $dataPoint->isQueryable(),
							'settable' => $dataPoint->isSettable(),
						]));
					}
				}

				return true;
			});
		}

		$this->logger->debug(
			'Consumed device found message',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
				'type' => 'store-local-device-message-consumer',
				'device' => [
					'id' => $device->getPlainId(),
				],
				'data' => $entity->toArray(),
			],
		);

		return true;
	}

}
