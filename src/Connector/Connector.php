<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Connector
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Connector;

use FastyBird\DevicesModule\Connectors as DevicesModuleConnectors;
use FastyBird\TuyaConnector\Clients;
use FastyBird\TuyaConnector\Consumers;
use Nette;
use React\EventLoop;

/**
 * Connector service container
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesModuleConnectors\IConnector
{

	use Nette\SmartObject;

	private const QUEUE_PROCESSING_INTERVAL = 0.01;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $consumerTimer;

	/** @var Clients\Client */
	private Clients\Client $client;

	/** @var Consumers\ClientsConsumer */
	private Consumers\ClientsConsumer $consumer;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/**
	 * @param Clients\Client $client
	 * @param Consumers\ClientsConsumer $consumer
	 * @param EventLoop\LoopInterface $eventLoop
	 */
	public function __construct(
		Clients\Client $client,
		Consumers\ClientsConsumer $consumer,
		EventLoop\LoopInterface $eventLoop
	) {
		$this->client = $client;

		$this->consumer = $consumer;

		$this->eventLoop = $eventLoop;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): void
	{
		$this->client->connect();

		$this->consumerTimer = $this->eventLoop->addPeriodicTimer(self::QUEUE_PROCESSING_INTERVAL, function (): void {
			$this->consumer->consume();
		});
	}

	/**
	 * {@inheritDoc}
	 */
	public function terminate(): void
	{
		$this->client->disconnect();

		if ($this->consumerTimer !== null) {
			$this->eventLoop->cancelTimer($this->consumerTimer);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasUnfinishedTasks(): bool
	{
		return !$this->consumer->isEmpty() && $this->consumerTimer !== null;
	}

}
