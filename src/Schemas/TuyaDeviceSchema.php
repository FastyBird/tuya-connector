<?php declare(strict_types = 1);

/**
 * TuyaDeviceSchema.php
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
use FastyBird\TuyaConnector\Entities;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * Tuya connector entity schema
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Schemas
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleSchemas\Devices\DeviceSchema<Entities\ITuyaDevice>
 */
final class TuyaDeviceSchema extends DevicesModuleSchemas\Devices\DeviceSchema
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_TUYA . '/device/' . Entities\TuyaDevice::DEVICE_TYPE;

	/**
	 * {@inheritDoc}
	 */
	public function getEntityClass(): string
	{
		return Entities\TuyaDevice::class;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
