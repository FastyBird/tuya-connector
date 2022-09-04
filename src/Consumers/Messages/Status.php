<?php declare(strict_types = 1);

/**
 * Status.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 * @since          0.13.0
 *
 * @date           04.09.22
 */

namespace FastyBird\TuyaConnector\Consumers\Messages;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Utilities as DevicesModuleUtilities;
use FastyBird\Metadata;
use FastyBird\TuyaConnector\Consumers\Consumer;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Mappers;
use Nette;
use Nette\Utils;
use Psr\Log;

/**
 * Device status message consumer
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Status implements Consumer
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/** @var Mappers\DataPoint */
	private Mappers\DataPoint $dataPointMapper;

	/** @var Helpers\Property */
	private Helpers\Property $propertyStateHelper;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param Mappers\DataPoint $dataPointMapper
	 * @param Helpers\Property $propertyStateHelper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		Mappers\DataPoint $dataPointMapper,
		Helpers\Property $propertyStateHelper,
		?Log\LoggerInterface $logger
	) {
		$this->devicesDataStorageRepository = $devicesDataStorageRepository;

		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->dataPointMapper = $dataPointMapper;
		$this->propertyStateHelper = $propertyStateHelper;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\DeviceStatus) {
			return false;
		}

		$deviceItem = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getIdentifier()
		);

		if ($deviceItem === null) {
			return true;
		}

		// Check device state...
		if (
			!$this->deviceConnectionStateManager->getState($deviceItem)->equalsValue(Metadata\Types\ConnectionStateType::STATE_CONNECTED)
		) {
			// ... and if it is not ready, set it to ready
			$this->deviceConnectionStateManager->setState(
				$deviceItem,
				Metadata\Types\ConnectionStateType::get(Metadata\Types\ConnectionStateType::STATE_CONNECTED)
			);
		}

		foreach ($entity->getDataPoints() as $dataPoint) {
			$property = $this->dataPointMapper->findProperty(
				$entity->getConnector(),
				$entity->getIdentifier(),
				$dataPoint->getIdentifier()
			);

			if ($property !== null) {
				$actualValue = DevicesModuleUtilities\ValueHelper::flattenValue(
					DevicesModuleUtilities\ValueHelper::normalizeValue(
						$property->getDataType(),
						$dataPoint->getValue(),
						$property->getFormat(),
						$property->getInvalid()
					)
				);

				$this->propertyStateHelper->setValue($property, Utils\ArrayHash::from([
					'actualValue' => $actualValue,
					'valid'       => true,
				]));
			}
		}

		$this->logger->debug(
			'Consumed device status message',
			[
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'status-message-consumer',
				'device' => [
					'id' => $deviceItem->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
