<?php declare(strict_types = 1);

/**
 * DeviceAttributeIdentifierType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Types
 * @since          0.13.0
 *
 * @date           25.08.22
 */

namespace FastyBird\TuyaConnector\Types;

use Consistence;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * Device attribute identifier types
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DeviceAttributeIdentifierType extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const IDENTIFIER_HARDWARE_MODEL = MetadataTypes\DeviceAttributeIdentifierType::IDENTIFIER_HARDWARE_MODEL;
	public const IDENTIFIER_HARDWARE_MAC_ADDRESS = MetadataTypes\DeviceAttributeIdentifierType::IDENTIFIER_HARDWARE_MAC_ADDRESS;
	public const IDENTIFIER_SERIAL_NUMBER = 'serial_number';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}