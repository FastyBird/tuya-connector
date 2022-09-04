<?php declare(strict_types = 1);

/**
 * DataPoint.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Mappers
 * @since          0.13.0
 *
 * @date           04.09.22
 */

namespace FastyBird\TuyaConnector\Mappers;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use Ramsey\Uuid;

/**
 * Device data point to module property mapper
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Mappers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DataPoint
{

	use Nette\SmartObject;

	/** @var Array<string, Uuid\UuidInterface> */
	private array $dataPointsToProperties = [];

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelPropertiesRepository */
	private DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository;

	/**
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	) {
		$this->devicesRepository = $devicesRepository;
		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;
	}

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $deviceIdentifier
	 * @param string $dataPointIdentifier
	 *
	 * @return MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|null
	 */
	public function findProperty(
		Uuid\UuidInterface $connector,
		string $deviceIdentifier,
		string $dataPointIdentifier
	): ?MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity {
		$key = $deviceIdentifier . '-' . $dataPointIdentifier;

		if (array_key_exists($key, $this->dataPointsToProperties)) {
			$property = $this->channelPropertiesRepository->findById($this->dataPointsToProperties[$key]);

			if ($property instanceof MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity) {
				return $property;
			}
		}

		$property = $this->loadProperty($connector, $deviceIdentifier, $dataPointIdentifier);

		if ($property !== null) {
			$this->dataPointsToProperties[$key] = $property->getId();
		}

		return $property;
	}

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $deviceIdentifier
	 * @param string $dataPointIdentifier
	 *
	 * @return MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|null
	 */
	private function loadProperty(
		Uuid\UuidInterface $connector,
		string $deviceIdentifier,
		string $dataPointIdentifier
	): ?MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity {
		$device = $this->devicesRepository->findByIdentifier($connector, $deviceIdentifier);

		if ($device === null) {
			return null;
		}

		$channels = $this->channelsRepository->findAllByDevice($device->getId());

		foreach ($channels as $channel) {
			$properties = $this->channelPropertiesRepository->findAllByChannel($channel->getId());

			foreach ($properties as $property) {
				if ($property->getIdentifier() === $dataPointIdentifier) {
					if ($property instanceof MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity) {
						return $property;
					}

					return null;
				}
			}
		}

		return null;
	}

}
