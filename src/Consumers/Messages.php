<?php declare(strict_types = 1);

/**
 * Messages.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 * @since          1.0.0
 *
 * @date           24.08.22
 */

namespace FastyBird\Connector\Tuya\Consumers;

use FastyBird\Connector\Tuya\Entities;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use Nette;
use Psr\Log;
use SplObjectStorage;
use SplQueue;
use function count;
use function sprintf;

/**
 * Clients message consumer proxy
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Messages
{

	use Nette\SmartObject;

	/** @var SplObjectStorage<Consumer, null> */
	private SplObjectStorage $consumers;

	/** @var SplQueue<Entities\Messages\Entity> */
	private SplQueue $queue;

	/**
	 * @param array<Consumer> $consumers
	 */
	public function __construct(
		array $consumers,
		private readonly Log\LoggerInterface $logger = new Log\NullLogger(),
	)
	{
		$this->consumers = new SplObjectStorage();
		$this->queue = new SplQueue();

		foreach ($consumers as $consumer) {
			$this->consumers->attach($consumer);
		}

		$this->logger->debug(
			sprintf('Registered %d messages consumers', count($this->consumers)),
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
				'type' => 'consumer',
			],
		);
	}

	public function append(Entities\Messages\Entity $entity): void
	{
		$this->queue->enqueue($entity);

		$this->logger->debug(
			'Appended new message into consumers queue',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
				'type' => 'consumer',
				'message' => $entity->toArray(),
			],
		);
	}

	public function consume(): void
	{
		$this->queue->rewind();

		if ($this->queue->isEmpty()) {
			return;
		}

		$this->consumers->rewind();

		if ($this->consumers->count() === 0) {
			$this->logger->error(
				'No consumer is registered, messages could not be consumed',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'consumer',
				],
			);

			return;
		}

		$entity = $this->queue->dequeue();

		foreach ($this->consumers as $consumer) {
			if ($consumer->consume($entity) === true) {
				return;
			}
		}

		$this->logger->error(
			'Message could not be consumed',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
				'type' => 'consumer',
				'message' => $entity->toArray(),
			],
		);
	}

	public function isEmpty(): bool
	{
		return $this->queue->isEmpty();
	}

}
