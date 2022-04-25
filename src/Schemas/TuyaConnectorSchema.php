<?php declare(strict_types = 1);

/**
 * TuyaConnectorSchema.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Schemas
 * @since          0.1.0
 *
 * @date           25.04.22
 */

namespace FastyBird\TuyaConnector\Schemas;

use FastyBird\DevicesModule\Schemas as DevicesModuleSchemas;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\TuyaConnector\Entities;

/**
 * Tuya connector entity schema
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Schemas
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleSchemas\Connectors\ConnectorSchema<Entities\ITuyaConnector>
 */
final class TuyaConnectorSchema extends DevicesModuleSchemas\Connectors\ConnectorSchema
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_TUYA . '/connector/' . Entities\TuyaConnector::CONNECTOR_TYPE;

	/**
	 * {@inheritDoc}
	 */
	public function getEntityClass(): string
	{
		return Entities\TuyaConnector::class;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
