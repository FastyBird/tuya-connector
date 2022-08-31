<?php declare(strict_types = 1);

/**
 * CloudDiscoveryMessage.php
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

namespace FastyBird\TuyaConnector\Consumers;

use Doctrine\DBAL;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types\DeviceAttributeIdentifier;
use FastyBird\TuyaConnector\Types\DevicePropertyIdentifier;
use Nette;
use Nette\Utils;
use Psr\Log;

/**
 * Cloud device discovery message consumer
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class CloudDiscoveryMessage implements Consumer
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

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDeviceAttributesRepository */
	private DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository;

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
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository
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
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository,
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

		$this->devicesDataStorageRepository = $devicesDataStorageRepository;
		$this->propertiesDataStorageRepository = $propertiesDataStorageRepository;
		$this->attributesDataStorageRepository = $attributesDataStorageRepository;

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
		if (!$entity instanceof Entities\Messages\DiscoveredCloudDevice) {
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

			/** @var DevicesModuleEntities\Devices\IDevice $deviceEntity */
			$deviceEntity = $this->databaseHelper->transaction(
				function () use ($entity, $connectorEntity): DevicesModuleEntities\Devices\IDevice {
					return $this->devicesManager->create(Utils\ArrayHash::from([
						'entity'     => Entities\TuyaDevice::class,
						'connector'  => $connectorEntity,
						'identifier' => $entity->getId(),
						'name'       => $entity->getName(),
					]));
				}
			);

			$this->logger->info(
				'Creating new device',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'cloud-discovery-message-consumer',
					'device' => [
						'id'         => $deviceEntity->getPlainId(),
						'identifier' => $entity->getId(),
						'address'    => $entity->getIp(),
						'name'       => $entity->getName(),
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

			if ($deviceEntity !== null) {
				/** @var Entities\TuyaDevice $deviceEntity */
				$deviceEntity = $this->databaseHelper->transaction(
					function () use ($entity, $deviceEntity): Entities\TuyaDevice {
						/** @var Entities\TuyaDevice $deviceEntity */
						$deviceEntity = $this->devicesManager->update($deviceEntity, Utils\ArrayHash::from([
							'name' => $entity->getName(),
						]));

						return $deviceEntity;
					}
				);

				$this->logger->debug(
					'Device was updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'   => 'cloud-discovery-message-consumer',
						'device'    => [
							'id'         => $deviceEntity->getPlainId(),
						],
					]
				);

			} else {
				$this->logger->error(
					'Device could not be updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'   => 'cloud-discovery-message-consumer',
						'device'    => [
							'id'         => $deviceItem->getId()->toString(),
						],
					]
				);
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
					'type'   => 'cloud-discovery-message-consumer',
					'device' => [
						'identifier' => $entity->getId(),
						'address'    => $entity->getIp(),
					],
				]
			);

			return true;
		}

		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->getLocalKey(),
			DevicePropertyIdentifier::IDENTIFIER_LOCAL_KEY
		);
		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->getIp(),
			DevicePropertyIdentifier::IDENTIFIER_IP_ADDRESS
		);
		$this->setDeviceAttribute(
			$deviceItem->getId(),
			$entity->getModel(),
			DeviceAttributeIdentifier::IDENTIFIER_HARDWARE_MODEL
		);
		$this->setDeviceAttribute(
			$deviceItem->getId(),
			$entity->getMac(),
			DeviceAttributeIdentifier::IDENTIFIER_HARDWARE_MAC_ADDRESS
		);
		$this->setDeviceAttribute(
			$deviceItem->getId(),
			$entity->getSn(),
			DeviceAttributeIdentifier::IDENTIFIER_SERIAL_NUMBER
		);

		$this->logger->debug(
			'Consumed device found message',
			[
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'cloud-discovery-message-consumer',
				'device' => [
					'id' => $deviceItem->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
