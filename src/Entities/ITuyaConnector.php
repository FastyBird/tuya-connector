<?php declare(strict_types = 1);

/**
 * ITuyaConnector.php
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

use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\TuyaConnector\Types;

/**
 * Tuya connector entity interface
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface ITuyaConnector extends DevicesModuleEntities\Connectors\IConnector
{

}
