<?php declare(strict_types = 1);

/**
 * LocalDiscovery.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Consumers\Messages;

use Doctrine\DBAL;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\TuyaConnector\Consumers\Consumer;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Nette;
use Nette\Utils;
use Psr\Log;

/**
 * Local device discovery message consumer
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class LocalDiscovery implements Consumer
{

	use Nette\SmartObject;
	use TConsumeDeviceProperty;
	use TConsumeDeviceAttribute;

	/** @var DevicesModuleModels\Connectors\IConnectorsRepository */
	private DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository;

	/** @var DevicesModuleModels\Devices\IDevicesRepository */
	private DevicesModuleModels\Devices\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\Devices\IDevicesManager */
	private DevicesModuleModels\Devices\IDevicesManager $devicesManager;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesRepository */
	private DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesManager */
	private DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager;

	/** @var DevicesModuleModels\Devices\Attributes\IAttributesRepository */
	private DevicesModuleModels\Devices\Attributes\IAttributesRepository $attributesRepository;

	/** @var DevicesModuleModels\Devices\Attributes\IAttributesManager */
	private DevicesModuleModels\Devices\Attributes\IAttributesManager $attributesManager;

	/** @var DevicesModuleModels\Channels\IChannelsRepository */
	private DevicesModuleModels\Channels\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\Channels\IChannelsManager */
	private DevicesModuleModels\Channels\IChannelsManager $channelsManager;

	/** @var DevicesModuleModels\Channels\Properties\IPropertiesRepository */
	private DevicesModuleModels\Channels\Properties\IPropertiesRepository $channelsPropertiesRepository;

	/** @var DevicesModuleModels\Channels\Properties\IPropertiesManager */
	private DevicesModuleModels\Channels\Properties\IPropertiesManager $channelsPropertiesManager;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDeviceAttributesRepository */
	private DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelPropertiesRepository */
	private DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelsPropertiesDataStorageRepository;

	/** @var Helpers\Database */
	private Helpers\Database $databaseHelper;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository
	 * @param DevicesModuleModels\Devices\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\Devices\IDevicesManager $devicesManager
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
	 * @param DevicesModuleModels\Devices\Attributes\IAttributesRepository $attributesRepository
	 * @param DevicesModuleModels\Devices\Attributes\IAttributesManager $attributesManager
	 * @param DevicesModuleModels\Channels\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\Channels\IChannelsManager $channelsManager
	 * @param DevicesModuleModels\Channels\Properties\IPropertiesRepository $channelsPropertiesRepository
	 * @param DevicesModuleModels\Channels\Properties\IPropertiesManager $channelsPropertiesManager
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelsPropertiesDataStorageRepository
	 * @param Helpers\Database $databaseHelper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository,
		DevicesModuleModels\Devices\IDevicesRepository $devicesRepository,
		DevicesModuleModels\Devices\IDevicesManager $devicesManager,
		DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository,
		DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager,
		DevicesModuleModels\Devices\Attributes\IAttributesRepository $attributesRepository,
		DevicesModuleModels\Devices\Attributes\IAttributesManager $attributesManager,
		DevicesModuleModels\Channels\IChannelsRepository $channelsRepository,
		DevicesModuleModels\Channels\IChannelsManager $channelsManager,
		DevicesModuleModels\Channels\Properties\IPropertiesRepository $channelsPropertiesRepository,
		DevicesModuleModels\Channels\Properties\IPropertiesManager $channelsPropertiesManager,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelsPropertiesDataStorageRepository,
		Helpers\Database $databaseHelper,
		?Log\LoggerInterface $logger = null
	) {
		$this->connectorsRepository = $connectorsRepository;
		$this->devicesRepository = $devicesRepository;
		$this->devicesManager = $devicesManager;
		$this->propertiesRepository = $propertiesRepository;
		$this->propertiesManager = $propertiesManager;
		$this->attributesRepository = $attributesRepository;
		$this->attributesManager = $attributesManager;
		$this->channelsRepository = $channelsRepository;
		$this->channelsManager = $channelsManager;
		$this->channelsPropertiesRepository = $channelsPropertiesRepository;
		$this->channelsPropertiesManager = $channelsPropertiesManager;

		$this->devicesDataStorageRepository = $devicesDataStorageRepository;
		$this->propertiesDataStorageRepository = $propertiesDataStorageRepository;
		$this->attributesDataStorageRepository = $attributesDataStorageRepository;
		$this->channelsPropertiesDataStorageRepository = $channelsPropertiesDataStorageRepository;

		$this->databaseHelper = $databaseHelper;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBAL\Exception
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\DiscoveredLocalDevice) {
			return false;
		}

		$deviceItem = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getId()
		);

		if ($deviceItem === null) {
			/** @var Entities\TuyaConnector|null $connectorEntity */
			$connectorEntity = $this->databaseHelper->query(
				function () use ($entity): ?Entities\TuyaConnector {
					$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
					$findConnectorQuery->byId($entity->getConnector());

					/** @var Entities\TuyaConnector|null $connector */
					$connector = $this->connectorsRepository->findOneBy(
						$findConnectorQuery,
						Entities\TuyaConnector::class
					);

					return $connector;
				}
			);

			if ($connectorEntity === null) {
				return true;
			}

			/** @var Entities\TuyaDevice $deviceEntity */
			$deviceEntity = $this->databaseHelper->transaction(
				function () use ($entity, $connectorEntity): Entities\TuyaDevice {
					/** @var Entities\TuyaDevice $deviceEntity */
					$deviceEntity = $this->devicesManager->create(Utils\ArrayHash::from([
						'entity'     => Entities\TuyaDevice::class,
						'connector'  => $connectorEntity,
						'identifier' => $entity->getId(),
					]));

					return $deviceEntity;
				}
			);

			$this->logger->info(
				'Creating new device',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'local-discovery-message-consumer',
					'device' => [
						'id'         => $deviceEntity->getPlainId(),
						'identifier' => $entity->getId(),
						'address'    => $entity->getIpAddress(),
					],
				]
			);
		} else {
			/** @var Entities\TuyaDevice|null $deviceEntity */
			$deviceEntity = $this->databaseHelper->query(
				function () use ($deviceItem): ?Entities\TuyaDevice {
					$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
					$findDeviceQuery->byId($deviceItem->getId());

					/** @var Entities\TuyaDevice|null $deviceEntity */
					$deviceEntity = $this->devicesRepository->findOneBy(
						$findDeviceQuery,
						Entities\TuyaDevice::class
					);

					return $deviceEntity;
				}
			);

			if ($deviceEntity === null) {
				$this->logger->error(
					'Device could not be updated',
					[
						'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'   => 'cloud-discovery-message-consumer',
						'device' => [
							'id' => $deviceItem->getId()->toString(),
						],
					]
				);

				return false;
			}
		}

		$deviceItem = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getId()
		);

		if ($deviceItem === null) {
			$this->logger->error(
				'Newly created device could not be loaded',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'local-discovery-message-consumer',
					'device' => [
						'identifier' => $entity->getId(),
						'address'    => $entity->getIpAddress(),
					],
				]
			);

			return true;
		}

		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->getIpAddress(),
			Types\DevicePropertyIdentifier::IDENTIFIER_IP_ADDRESS
		);
		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->getVersion(),
			Types\DevicePropertyIdentifier::IDENTIFIER_PROTOCOL_VERSION
		);
		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->getLocalKey(),
			Types\DevicePropertyIdentifier::IDENTIFIER_LOCAL_KEY
		);
		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->isEncrypted(),
			Types\DevicePropertyIdentifier::IDENTIFIER_ENCRYPTED
		);

		$this->databaseHelper->transaction(function () use ($entity, $deviceEntity): bool {
			$findChannelQuery = new DevicesModuleQueries\FindChannelsQuery();
			$findChannelQuery->byIdentifier(Types\DataPoint::DATA_POINT_LOCAL);
			$findChannelQuery->forDevice($deviceEntity);

			$channelEntity = $this->channelsRepository->findOneBy($findChannelQuery);

			if ($channelEntity === null) {
				$channelEntity = $this->channelsManager->create(Utils\ArrayHash::from([
					'device'     => $deviceEntity,
					'identifier' => Types\DataPoint::DATA_POINT_LOCAL,
				]));

				$this->logger->debug(
					'Creating new device channel',
					[
						'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'    => 'cloud-discovery-message-consumer',
						'device'  => [
							'id' => $deviceEntity->getPlainId(),
						],
						'channel' => [
							'id' => $channelEntity->getPlainId(),
						],
					]
				);
			}

			foreach ($entity->getDataPoints() as $dataPoint) {
				$propertyItem = $this->channelsPropertiesDataStorageRepository->findByIdentifier(
					$channelEntity->getId(),
					$dataPoint->getCode()
				);

				if ($propertyItem === null) {
					$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
						'channel'    => $channelEntity,
						'entity'     => DevicesModuleEntities\Channels\Properties\DynamicProperty::class,
						'identifier' => $dataPoint->getCode(),
						'name'       => $dataPoint->getCode(),
						'dataType'   => $dataPoint->getDataType(),
						'unit'       => $dataPoint->getUnit(),
						'format'     => $dataPoint->getFormat(),
						'queryable'  => $dataPoint->isQueryable(),
						'settable'   => $dataPoint->isSettable(),
					]));

				} else {
					$findPropertyQuery = new DevicesModuleQueries\FindChannelPropertiesQuery();
					$findPropertyQuery->byId($propertyItem->getId());

					$propertyEntity = $this->channelsPropertiesRepository->findOneBy($findPropertyQuery);

					if ($propertyEntity !== null) {
						$this->channelsPropertiesManager->update($propertyEntity, Utils\ArrayHash::from([
							'name'      => $propertyEntity->getName() ?? $dataPoint->getCode(),
							'dataType'  => $dataPoint->getDataType(),
							'unit'      => $dataPoint->getUnit(),
							'format'    => $dataPoint->getFormat(),
							'queryable' => $dataPoint->isQueryable(),
							'settable'  => $dataPoint->isSettable(),
						]));

					} else {
						$this->logger->error(
							'Channel property could not be updated',
							[
								'source'   => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
								'type'     => 'cloud-discovery-message-consumer',
								'device'   => [
									'id' => $channelEntity->getDevice()->getId()->toString(),
								],
								'channel'  => [
									'id' => $channelEntity->getId()->toString(),
								],
								'property' => [
									'id' => $propertyItem->getId(),
								],
							]
						);
					}
				}
			}

			return true;
		});

		$this->logger->debug(
			'Consumed device found message',
			[
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'local-discovery-message-consumer',
				'device' => [
					'id' => $deviceItem->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
