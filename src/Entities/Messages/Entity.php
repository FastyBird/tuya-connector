<?php declare(strict_types = 1);

/**
 * Entity.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Entities\Messages;

use FastyBird\TuyaConnector\Types;

/**
 * Tuya base message data entity interface
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface Entity
{

	/**
	 * @return Types\MessageSource
	 */
	public function getSource(): Types\MessageSource;

	/**
	 * @return Array<string, mixed>
	 */
	public function toArray(): array;

}