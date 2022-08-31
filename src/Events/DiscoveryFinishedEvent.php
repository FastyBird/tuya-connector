<?php declare(strict_types = 1);

/**
 * DiscoveryFinishedEvent.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Events
 * @since          0.13.0
 *
 * @date           29.08.22
 */

namespace FastyBird\TuyaConnector\Events;

use Symfony\Contracts\EventDispatcher;

/**
 * Event fired after devices discovery is finished
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Events
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DiscoveryFinishedEvent extends EventDispatcher\Event
{

}
