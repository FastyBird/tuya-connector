<?php declare(strict_types = 1);

/**
 * Properties.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Subscribers
 * @since          0.13.0
 *
 * @date           05.09.22
 */

namespace FastyBird\TuyaConnector\Subscribers;

use Doctrine\Common;
use Doctrine\ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Types;
use Nette;
use Nette\Utils;

/**
 * Doctrine entities events
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Subscribers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Properties implements Common\EventSubscriber
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesManager */
	private DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager;

	/**
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
	 */
	public function __construct(
		DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
	) {
		$this->propertiesManager = $propertiesManager;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSubscribedEvents(): array
	{
		return [
			ORM\Events::postPersist,
		];
	}

	/**
	 * @param ORM\Event\LifecycleEventArgs $eventArgs
	 *
	 * @return void
	 */
	public function postPersist(ORM\Event\LifecycleEventArgs $eventArgs): void
	{
		// onFlush was executed before, everything already initialized
		$entity = $eventArgs->getObject();

		// Check for valid entity
		if (!$entity instanceof Entities\TuyaDevice) {
			return;
		}

		$stateProperty = $entity->getProperty(Types\DevicePropertyIdentifier::IDENTIFIER_STATE);

		if ($stateProperty !== null) {
			$entity->removeProperty($stateProperty);
		}

		$this->propertiesManager->create(Utils\ArrayHash::from([
			'device'     => $entity,
			'entity'     => DevicesModuleEntities\Devices\Properties\DynamicProperty::class,
			'identifier' => Types\DevicePropertyIdentifier::IDENTIFIER_STATE,
			'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_ENUM),
			'unit'       => null,
			'format'     => [
				MetadataTypes\ConnectionStateType::STATE_CONNECTED,
				MetadataTypes\ConnectionStateType::STATE_DISCONNECTED,
				MetadataTypes\ConnectionStateType::STATE_STOPPED,
				MetadataTypes\ConnectionStateType::STATE_LOST,
				MetadataTypes\ConnectionStateType::STATE_UNKNOWN,
			],
			'settable'   => false,
			'queryable'  => false,
		]));
	}

}
