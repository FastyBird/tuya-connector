<?php declare(strict_types = 1);

/**
 * TConsumeDeviceProperty.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 * @since          0.13.0
 *
 * @date           31.08.22
 */

namespace FastyBird\TuyaConnector\Consumers;

use Doctrine\DBAL;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\TuyaConnector\Helpers;
use Nette\Utils;
use Psr\Log;
use Ramsey\Uuid;

/**
 * Device ip address consumer trait
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @property-read DevicesModuleModels\Devices\IDevicesRepository $devicesRepository
 * @property-read DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository
 * @property-read DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
 * @property-read DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository
 * @property-read Helpers\Database $databaseHelper
 * @property-read Log\LoggerInterface $logger
 */
trait TConsumeDeviceProperty
{

	/**
	 * @param Uuid\UuidInterface $deviceId
	 * @param string|null $value
	 * @param string $identifier
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function setDeviceProperty(
		Uuid\UuidInterface $deviceId,
		?string $value,
		string $identifier
	): void {
		$propertyItem = $this->propertiesDataStorageRepository->findByIdentifier(
			$deviceId,
			$identifier
		);

		if ($propertyItem !== null && $value === null) {
			/** @var DevicesModuleEntities\Devices\Properties\IProperty|null $propertyEntity */
			$propertyEntity = $this->databaseHelper->query(
				function () use ($propertyItem): ?DevicesModuleEntities\Devices\Properties\IProperty {
					$findPropertyQuery = new DevicesModuleQueries\FindDevicePropertiesQuery();
					$findPropertyQuery->byId($propertyItem->getId());

					return $this->propertiesRepository->findOneBy($findPropertyQuery);
				}
			);

			if ($propertyEntity !== null) {
				$this->databaseHelper->transaction(
					function () use ($propertyEntity): void {
						$this->propertiesManager->delete($propertyEntity);
					}
				);
			}

			return;
		}

		if ($value === null) {
			return;
		}

		if (
			$propertyItem instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			&& $propertyItem->getValue() === $value
		) {
			return;
		}

		if (
			$propertyItem !== null
			&& !$propertyItem instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
		) {
			/** @var DevicesModuleEntities\Devices\Properties\IProperty|null $propertyEntity */
			$propertyEntity = $this->databaseHelper->query(
				function () use ($propertyItem): ?DevicesModuleEntities\Devices\Properties\IProperty {
					$findPropertyQuery = new DevicesModuleQueries\FindDevicePropertiesQuery();
					$findPropertyQuery->byId($propertyItem->getId());

					return $this->propertiesRepository->findOneBy($findPropertyQuery);
				}
			);

			if ($propertyEntity !== null) {
				$this->databaseHelper->transaction(function () use ($propertyEntity): void {
					$this->propertiesManager->delete($propertyEntity);
				});

				$this->logger->warning(
					'Device property is not valid type',
					[
						'source'   => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'     => 'message-consumer',
						'device'   => [
							'id' => $deviceId->toString(),
						],
						'property' => [
							'id'         => $propertyEntity->getPlainId(),
							'identifier' => $identifier,
						],
					]
				);
			}

			$propertyItem = null;
		}

		if ($propertyItem === null) {
			/** @var DevicesModuleEntities\Devices\IDevice|null $deviceEntity */
			$deviceEntity = $this->databaseHelper->query(
				function () use ($deviceId): ?DevicesModuleEntities\Devices\IDevice {
					$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
					$findDeviceQuery->byId($deviceId);

					return $this->devicesRepository->findOneBy($findDeviceQuery);
				}
			);

			if ($deviceEntity === null) {
				return;
			}

			/** @var DevicesModuleEntities\Devices\Properties\IProperty $propertyEntity */
			$propertyEntity = $this->databaseHelper->transaction(
				function () use (
					$deviceEntity,
					$value,
					$identifier
				): DevicesModuleEntities\Devices\Properties\IProperty {
					return $this->propertiesManager->create(Utils\ArrayHash::from([
						'entity'     => DevicesModuleEntities\Devices\Properties\StaticProperty::class,
						'device'     => $deviceEntity,
						'identifier' => $identifier,
						'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
						'settable'   => false,
						'queryable'  => false,
						'value'      => $value,
					]));
				}
			);

			$this->logger->debug(
				'Device ip address property was created',
				[
					'source'   => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'     => 'message-consumer',
					'device'   => [
						'id' => $deviceId->toString(),
					],
					'property' => [
						'id'         => $propertyEntity->getPlainId(),
						'identifier' => $identifier,
					],
				]
			);

		} else {
			/** @var DevicesModuleEntities\Devices\Properties\IProperty|null $propertyEntity */
			$propertyEntity = $this->databaseHelper->query(
				function () use ($propertyItem): ?DevicesModuleEntities\Devices\Properties\IProperty {
					$findPropertyQuery = new DevicesModuleQueries\FindDevicePropertiesQuery();
					$findPropertyQuery->byId($propertyItem->getId());

					return $this->propertiesRepository->findOneBy($findPropertyQuery);
				}
			);

			if ($propertyEntity !== null) {
				/** @var DevicesModuleEntities\Devices\Properties\IProperty $propertyEntity */
				$propertyEntity = $this->databaseHelper->transaction(
					function () use ($value, $propertyEntity): DevicesModuleEntities\Devices\Properties\IProperty {
						return $this->propertiesManager->update($propertyEntity, Utils\ArrayHash::from([
							'value' => $value,
						]));
					}
				);

				$this->logger->debug(
					'Device ip address property was updated',
					[
						'source'   => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'     => 'message-consumer',
						'device'   => [
							'id' => $deviceId->toString(),
						],
						'property' => [
							'id'         => $propertyEntity->getPlainId(),
							'identifier' => $identifier,
						],
					]
				);

			} else {
				$this->logger->error(
					'Device ip address property could not be updated',
					[
						'source'   => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'     => 'message-consumer',
						'device'   => [
							'id' => $deviceId->toString(),
						],
						'property' => [
							'id'         => $propertyItem->getId()->toString(),
							'identifier' => $identifier,
						],
					]
				);
			}
		}
	}

}
