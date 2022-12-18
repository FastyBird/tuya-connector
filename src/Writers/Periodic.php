<?php declare(strict_types = 1);

/**
 * Periodic.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Writers
 * @since          0.13.0
 *
 * @date           14.12.22
 */

namespace FastyBird\Connector\Tuya\Writers;

use DateTimeInterface;
use FastyBird\Connector\Tuya\Clients;
use FastyBird\Connector\Tuya\Entities;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use React\EventLoop;
use function array_key_exists;
use function assert;
use function in_array;

/**
 * Periodic properties writer
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Periodic implements Writer
{

	use Nette\SmartObject;

	public const NAME = 'periodic';

	private const HANDLER_START_DELAY = 5.0;

	private const HANDLER_DEBOUNCE_INTERVAL = 500;

	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	private const HANDLER_PENDING_DELAY = 2_000;

	/** @var Array<string> */
	private array $processedDevices = [];

	/** @var Array<string, DateTimeInterface> */
	private array $processedProperties = [];

	private Entities\TuyaConnector|null $connector = null;

	private Clients\Client|null $client = null;

	private EventLoop\TimerInterface|null $handlerTimer = null;

	public function __construct(
		private readonly DevicesUtilities\DeviceConnection $deviceConnectionManager,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStates,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
	}

	public function connect(
		Entities\TuyaConnector $connector,
		Clients\Client $client,
	): void
	{
		$this->connector = $connector;
		$this->client = $client;

		$this->processedDevices = [];
		$this->processedProperties = [];

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->registerLoopHandler();
			},
		);
	}

	public function disconnect(): void
	{
		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);

			$this->handlerTimer = null;
		}
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function handleCommunication(): void
	{
		foreach ($this->processedProperties as $index => $processedProperty) {
			if (
				(float) $this->dateTimeFactory->getNow()->format('Uv') - (float) $processedProperty->format(
					'Uv',
				) >= 500
			) {
				unset($this->processedProperties[$index]);
			}
		}

		foreach ($this->connector?->getDevices() ?? [] as $device) {
			assert($device instanceof Entities\TuyaDevice);

			if (
				!in_array($device->getPlainId(), $this->processedDevices, true)
				&& $this->deviceConnectionManager->getState($device)->equalsValue(
					MetadataTypes\ConnectionState::STATE_CONNECTED,
				)
			) {
				$this->processedDevices[] = $device->getPlainId();

				if ($this->writeChannelsProperty($device)) {
					$this->registerLoopHandler();

					return;
				}
			}
		}

		$this->processedDevices = [];

		$this->registerLoopHandler();
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function writeChannelsProperty(Entities\TuyaDevice $device): bool
	{
		$now = $this->dateTimeFactory->getNow();

		foreach ($device->getChannels() as $channel) {
			foreach ($channel->getProperties() as $property) {
				if (!$property instanceof DevicesEntities\Channels\Properties\Dynamic) {
					continue;
				}

				$state = $this->channelPropertiesStates->getValue($property);

				if ($state === null) {
					continue;
				}

				$expectedValue = DevicesUtilities\ValueHelper::flattenValue($state->getExpectedValue());

				if (
					$property->isSettable()
					&& $expectedValue !== null
					&& $state->isPending() === true
				) {
					$debounce = array_key_exists(
						$property->getPlainId(),
						$this->processedProperties,
					)
						? $this->processedProperties[$property->getPlainId()]
						: false;

					if (
						$debounce !== false
						&& (float) $now->format('Uv') - (float) $debounce->format(
							'Uv',
						) < self::HANDLER_DEBOUNCE_INTERVAL
					) {
						continue;
					}

					unset($this->processedProperties[$property->getPlainId()]);

					$pending = $state->getPending();

					if (
						$pending === true
						|| (
							$pending instanceof DateTimeInterface
							&& (float) $now->format('Uv') - (float) $pending->format('Uv') > self::HANDLER_PENDING_DELAY
						)
					) {
						$this->processedProperties[$property->getPlainId()] = $now;

						$this->client?->writeChannelProperty($device, $channel, $property);

						return true;
					}
				}
			}
		}

		return false;
	}

	private function registerLoopHandler(): void
	{
		$this->handlerTimer = $this->eventLoop->addTimer(
			self::HANDLER_PROCESSING_INTERVAL,
			function (): void {
				$this->handleCommunication();
			},
		);
	}

}
