<?php declare(strict_types = 1);

/**
 * TuyaConnector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           25.04.22
 */

namespace FastyBird\TuyaConnector\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * @ORM\Entity
 */
class TuyaConnector extends DevicesModuleEntities\Connectors\Connector implements ITuyaConnector
{

	public const CONNECTOR_TYPE = 'tuya';

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDiscriminatorName(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource()
	{
		return MetadataTypes\ConnectorSourceType::get(MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_TUYA);
	}

}
