<?php declare(strict_types = 1);

/**
 * TuyaDevice.php
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

namespace FastyBird\Connector\Tuya\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;

/**
 * @ORM\Entity
 */
class TuyaDevice extends DevicesEntities\Devices\Device
{

	public const DEVICE_TYPE = 'tuya';

	public function getType(): string
	{
		return self::DEVICE_TYPE;
	}

	public function getDiscriminatorName(): string
	{
		return self::DEVICE_TYPE;
	}

	public function getSource(): MetadataTypes\ConnectorSource
	{
		return MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA);
	}

}
