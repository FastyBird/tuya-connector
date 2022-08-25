<?php declare(strict_types = 1);

/**
 * ConnectorFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     common
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector;

use FastyBird\DevicesModule\Connectors as DevicesModuleConnectors;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;

/**
 * Connector service container factory
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ConnectorFactory implements DevicesModuleConnectors\IConnectorFactory
{

	use Nette\SmartObject;

	/** @var Clients\DevicesClientFactory */
	private Clients\DevicesClientFactory $devicesClientFactory;

	/** @var Connector\ConnectorFactory */
	private Connector\ConnectorFactory $connectorFactory;

	/**
	 * @param Clients\DevicesClientFactory $devicesClientFactory
	 * @param Connector\ConnectorFactory $connectorFactory
	 */
	public function __construct(
		Clients\DevicesClientFactory $devicesClientFactory,
		Connector\ConnectorFactory $connectorFactory
	) {
		$this->devicesClientFactory = $devicesClientFactory;
		$this->connectorFactory = $connectorFactory;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return Entities\TuyaConnectorEntity::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function create(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	): DevicesModuleConnectors\IConnector {
		return $this->connectorFactory->create($this->devicesClientFactory->create($connector));
	}

}
