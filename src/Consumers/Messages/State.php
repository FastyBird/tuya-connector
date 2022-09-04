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

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		?Log\LoggerInterface $logger
	) {
		$this->devicesDataStorageRepository = $devicesDataStorageRepository;

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

		// Check device state...
		if (
			!$this->deviceConnectionStateManager->getState($deviceItem)->equalsValue(Metadata\Types\ConnectionStateType::STATE_CONNECTED)
		) {
			// ... and if it is not ready, set it to ready
			$this->deviceConnectionStateManager->setState(
				$deviceItem,
				Metadata\Types\ConnectionStateType::get($entity->isOnline() ? Metadata\Types\ConnectionStateType::STATE_CONNECTED : Metadata\Types\ConnectionStateType::STATE_DISCONNECTED)
			);
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
