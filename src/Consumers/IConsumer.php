<?php declare(strict_types = 1);

/**
 * IConsumer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Consumers;

use FastyBird\TuyaConnector\Entities;

/**
 * Clients messages consumer interface
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IConsumer
{

	/**
	 * @param Entities\Messages\IEntity $entity
	 *
	 * @return bool
	 */
	public function consume(Entities\Messages\IEntity $entity): bool;

}