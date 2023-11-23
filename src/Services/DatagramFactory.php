<?php declare(strict_types = 1);

/**
 * MulticastFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:VieraConnector!
 * @subpackage     Clients
 * @since          1.0.0
 *
 * @date           14.08.23
 */

namespace FastyBird\Connector\Tuya\Services;

use Nette;
use React\Datagram;
use React\EventLoop;
use React\Promise;

/**
 * React datagram server factory
 *
 * @package        FastyBird:VieraConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DatagramFactory
{

	use Nette\SmartObject;

	public function __construct(
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
	}

	/**
	 * @return Promise\PromiseInterface<Datagram\Socket>
	 */
	public function create(string $address, int $port): Promise\PromiseInterface
	{
		return (new Datagram\Factory($this->eventLoop))->createServer($address . ':' . $port);
	}

}
