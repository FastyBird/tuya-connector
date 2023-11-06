<?php declare(strict_types = 1);

/**
 * StoreCloudDevice.php
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

/**
 * Store cloud found device details message consumer
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class StoreCloudDevice implements Queue\Consumer
{

	use Nette\SmartObject;
	use DeviceProperty;

	public function __construct(
		protected readonly Tuya\Logger $logger,
		protected readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		protected readonly DevicesModels\Entities\Devices\Properties\PropertiesRepository $devicesPropertiesRepository,
		protected readonly DevicesModels\Entities\Devices\Properties\PropertiesManager $devicesPropertiesManager,
		protected readonly DevicesUtilities\Database $databaseHelper,
		private readonly DevicesModels\Entities\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesManager $channelsPropertiesManager,
		private readonly DevicesModels\Entities\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Entities\Devices\DevicesManager $devicesManager,
		private readonly DevicesModels\Entities\Channels\ChannelsManager $channelsManager,
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
		if (!$entity instanceof Entities\Messages\StoreCloudDevice) {
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
					'type' => 'store-cloud-device-message-consumer',
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
					'type' => 'store-cloud-device-message-consumer',
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);
		}

		$this->setDeviceProperty(
			$device->getId(),
			$entity->getLocalKey(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::LOCAL_KEY,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::LOCAL_KEY),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getIpAddress(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::IP_ADDRESS,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::IP_ADDRESS),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getCategory(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::CATEGORY,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::CATEGORY),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getIcon(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::ICON,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::ICON),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getLatitude(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::LATITUDE,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::LATITUDE),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getLongitude(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::LONGITUDE,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::LONGITUDE),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getProductId(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::PRODUCT_ID,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::PRODUCT_ID),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getProductName(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::PRODUCT_NAME,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::PRODUCT_NAME),
		);

		$this->setDeviceProperty(
			$device->getId(),
			$entity->getModel(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::MODEL,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::MODEL),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getMac(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::MAC_ADDRESS,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::MAC_ADDRESS),
		);
		$this->setDeviceProperty(
			$device->getId(),
			$entity->getSn(),
			MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
			Types\DevicePropertyIdentifier::SERIAL_NUMBER,
			DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::SERIAL_NUMBER),
		);

		$this->databaseHelper->transaction(function () use ($entity, $device): bool {
			$findChannelQuery = new DevicesQueries\FindChannels();
			$findChannelQuery->byIdentifier(Types\DataPoint::CLOUD);
			$findChannelQuery->forDevice($device);

			$channel = $this->channelsRepository->findOneBy($findChannelQuery);

			if ($channel === null) {
				$channel = $this->channelsManager->create(Utils\ArrayHash::from([
					'device' => $device,
					'identifier' => Types\DataPoint::CLOUD,
				]));

				$this->logger->debug(
					'Device channel was created',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
						'type' => 'store-cloud-device-message-consumer',
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
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
						'dataType' => $dataPoint->getDataType(),
						'unit' => $dataPoint->getUnit(),
						'format' => $dataPoint->getFormat(),
						'scale' => $dataPoint->getScale(),
						'step' => $dataPoint->getStep(),
						'queryable' => $dataPoint->isQueryable(),
						'settable' => $dataPoint->isSettable(),
					]));

				} else {
					$this->channelsPropertiesManager->update($property, Utils\ArrayHash::from([
						'dataType' => $dataPoint->getDataType(),
						'unit' => $dataPoint->getUnit(),
						'format' => $dataPoint->getFormat(),
						'scale' => $dataPoint->getScale(),
						'step' => $dataPoint->getStep(),
						'queryable' => $dataPoint->isQueryable(),
						'settable' => $dataPoint->isSettable(),
					]));
				}
			}

			return true;
		});

		$this->logger->debug(
			'Consumed device found message',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
				'type' => 'store-cloud-device-message-consumer',
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'data' => $entity->toArray(),
			],
		);

		return true;
	}

}
