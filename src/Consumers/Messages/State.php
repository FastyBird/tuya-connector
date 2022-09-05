<?php declare(strict_types = 1);

/**
 * State.php
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
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\TuyaConnector\Consumers\Consumer;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Helpers;
use Nette;
use Psr\Log;

/**
 * Device state message consumer
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class State implements Consumer
{

	use Nette\SmartObject;

	/** @var Helpers\Property */
	private Helpers\Property $propertyStateHelper;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelPropertiesRepository */
	private DevicesModuleModels\DataStorage\IChannelPropertiesRepository$channelPropertiesRepository;

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param Helpers\Property $propertyStateHelper
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		Helpers\Property $propertyStateHelper,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		?Log\LoggerInterface $logger
	) {
		$this->propertyStateHelper = $propertyStateHelper;

		$this->devicesDataStorageRepository = $devicesDataStorageRepository;
		$this->devicePropertiesRepository = $devicePropertiesRepository;
		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;

		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\DeviceState) {
			return false;
		}

		$deviceItem = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getIdentifier()
		);

		if ($deviceItem === null) {
			return true;
		}

		$actualDeviceState = Metadata\Types\ConnectionStateType::get($entity->isOnline() ? Metadata\Types\ConnectionStateType::STATE_CONNECTED : Metadata\Types\ConnectionStateType::STATE_DISCONNECTED);

		// Check device state...
		if (
			!$this->deviceConnectionStateManager->getState($deviceItem)->equals($actualDeviceState)
		) {
			// ... and if it is not ready, set it to ready
			$this->deviceConnectionStateManager->setState(
				$deviceItem,
				$actualDeviceState
			);

			if ($actualDeviceState->equalsValue(Metadata\Types\ConnectionStateType::STATE_DISCONNECTED)) {
				$devicePropertiesItems = $this->devicePropertiesRepository->findAllByDevice(
					$deviceItem->getId(),
					MetadataEntities\Modules\DevicesModule\DeviceDynamicPropertyEntity::class
				);

				foreach ($devicePropertiesItems as $propertyItem) {
					$this->propertyStateHelper->setValue(
						$propertyItem,
						Nette\Utils\ArrayHash::from([
							'valid' => false,
						])
					);
				}

				$channelItems = $this->channelsRepository->findAllByDevice($deviceItem->getId());

				foreach ($channelItems as $channelItem) {
					$channelProperties = $this->channelPropertiesRepository->findAllByChannel(
						$channelItem->getId(),
						MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity::class
					);

					foreach ($channelProperties as $propertyItem) {
						$this->propertyStateHelper->setValue(
							$propertyItem,
							Nette\Utils\ArrayHash::from([
								'valid' => false,
							])
						);
					}
				}
			}
		}

		$this->logger->debug(
			'Consumed device state message',
			[
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'state-message-consumer',
				'device' => [
					'id' => $deviceItem->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
