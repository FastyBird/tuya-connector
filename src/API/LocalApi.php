<?php declare(strict_types = 1);

/**
 * LocalApi.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 * @since          0.13.0
 *
 * @date           31.08.22
 */

namespace FastyBird\TuyaConnector\API;

use DateTimeInterface;
use Evenement;
use FastyBird\DateTimeFactory;
use FastyBird\Metadata;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Exceptions;
use FastyBird\TuyaConnector\Types;
use Nette;
use Psr\Log;
use React\EventLoop;
use React\Promise;
use React\Socket;
use Throwable;

/**
 * Local UDP device interface
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class LocalApi implements Evenement\EventEmitterInterface
{

	use Nette\SmartObject;
	use Evenement\EventEmitterTrait;

	private const SOCKET_PORT = 6668;

	private const HEARTBEAT_INTERVAL = 7.0;
	private const HEARTBEAT_SEQ_NO = -100;
	private const HEARTBEAT_TIMEOUT = 10.0;

	private const WAIT_FOR_REPLY_TIMEOUT = 5.0;

	/** @var string */
	private string $identifier;

	/** @var string */
	private string $gateway;

	/** @var string */
	private string $localKey;

	/** @var string */
	private string $ipAddress;

	/** @var int */
	private int $sequenceNr = 0;

	/** @var DateTimeInterface|null */
	private ?DateTimeInterface $lastHeartbeat = null;

	/** @var Array<int, Promise\Deferred> */
	private array $messagesListeners = [];

	/** @var Array<int, EventLoop\TimerInterface> */
	private array $messagesListenersTimers = [];

	/** @var EventLoop\TimerInterface|null */
	private EventLoop\TimerInterface|null $heartBeatTimer = null;

	/** @var Types\DeviceProtocolVersion */
	private Types\DeviceProtocolVersion $protocolVersion;

	/** @var Socket\ConnectionInterface|null */
	private ?Socket\ConnectionInterface $connection = null;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param string $identifier
	 * @param string|null $gateway
	 * @param string $localKey
	 * @param string $ipAddress
	 * @param Types\DeviceProtocolVersion $protocolVersion
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		string $identifier,
		?string $gateway,
		string $localKey,
		string $ipAddress,
		Types\DeviceProtocolVersion $protocolVersion,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->identifier = $identifier;
		$this->gateway = $gateway ?? $identifier;
		$this->localKey = $localKey;
		$this->ipAddress = $ipAddress;
		$this->protocolVersion = $protocolVersion;

		$this->dateTimeFactory = $dateTimeFactory;
		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @return Promise\PromiseInterface
	 */
	public function connect(): Promise\PromiseInterface
	{
		$this->messagesListeners = [];
		$this->messagesListenersTimers = [];

		$this->heartBeatTimer = null;
		$this->lastHeartbeat = null;

		$deferred = new Promise\Deferred();

		try {
			$connector = new Socket\Connector($this->eventLoop);

			$connector->connect($this->ipAddress . ':' . self::SOCKET_PORT)
				->then(function (Socket\ConnectionInterface $connection) use ($deferred): void {
					$this->connection = $connection;

					$this->connection->on('data', function ($chunk) {
						$message = $this->decodePayload($chunk);

						if ($message !== null) {
							var_dump($message->toArray());

							if (array_key_exists($message->getSequence(), $this->messagesListeners)) {
								$this->messagesListeners[$message->getSequence()]->resolve($message);
							}

							if ($message->getCommand()->equalsValue(Types\LocalDeviceCommand::CMD_HEART_BEAT)) {
								$this->lastHeartbeat = $this->dateTimeFactory->getNow();

								$this->logger->debug(
									'Device has replied to heartbeat',
									[
										'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
										'type'   => 'localapi-api',
										'device' => [
											'identifier' => $this->identifier,
										],
									]
								);
							}

							if ($message->getCommand()->equalsValue(Types\LocalDeviceCommand::CMD_STATUS)) {
								$this->logger->debug(
									'Device has reported its status',
									[
										'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
										'type'    => 'localapi-api',
										'device'  => [
											'identifier' => $this->identifier,
										],
										'message' => [
											'data' => $message->getData(),
										],
									]
								);
							}
						}
					});

					$this->connection->on('error', function (Throwable $ex) {
						$this->logger->error(
							'An error occurred on device connection',
							[
								'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
								'type'      => 'localapi-api',
								'device'    => [
									'identifier' => $this->identifier,
								],
								'exception' => [
									'message' => $ex->getMessage(),
									'code'    => $ex->getCode(),
								],
							]
						);

						$this->disconnect();
					});

					$this->connection->on('close', function () {
						$this->logger->debug(
							'Connection with device was closed',
							[
								'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
								'type'   => 'localapi-api',
								'device' => [
									'identifier' => $this->identifier,
								],
							]
						);
					});

					$this->heartBeatTimer = $this->eventLoop->addPeriodicTimer(
						self::HEARTBEAT_INTERVAL,
						function (): void {
							$this->logger->debug(
								'Sending ping to device',
								[
									'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
									'type'   => 'localapi-api',
									'device' => [
										'identifier' => $this->identifier,
									],
								]
							);

							$this->sendRequest(
								Types\LocalDeviceCommand::get(Types\LocalDeviceCommand::CMD_HEART_BEAT),
								null,
								self::HEARTBEAT_SEQ_NO
							);
						}
					);

					$deferred->resolve();
				})
				->otherwise(function (Throwable $ex) use ($deferred): void {
					$deferred->reject($ex);
				});
		} catch (Throwable $ex) {
			$this->logger->error(
				'Could not create connector',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'      => 'localapi-api',
					'device'    => [
						'identifier' => $this->identifier,
					],
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]
			);

			$deferred->reject();
		}

		return $deferred->promise();
	}

	/**
	 * @return void
	 */
	public function disconnect(): void
	{
		$this->connection?->close();

		if ($this->heartBeatTimer !== null) {
			$this->eventLoop->cancelTimer($this->heartBeatTimer);
		}

		foreach ($this->messagesListenersTimers as $timer) {
			$this->eventLoop->cancelTimer($timer);
		}

		foreach ($this->messagesListeners as $listener) {
			$listener->reject(new Exceptions\LocalApiCall('Closing connection to device'));
		}
	}

	/**
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return $this->connection !== null
			&& (
				$this->lastHeartbeat === null
				|| ($this->dateTimeFactory->getNow()->getTimestamp() - $this->lastHeartbeat->getTimestamp()) < self::HEARTBEAT_TIMEOUT
			);
	}

	/**
	 * @return Promise\PromiseInterface
	 */
	public function readStates(): Promise\PromiseInterface
	{
		$deferred = new Promise\Deferred();

		$sequenceNr = $this->sendRequest(Types\LocalDeviceCommand::get(Types\LocalDeviceCommand::CMD_DP_QUERY));

		$this->messagesListeners[$sequenceNr] = $deferred;

		$this->messagesListenersTimers[$sequenceNr] = $this->eventLoop->addTimer(
			self::WAIT_FOR_REPLY_TIMEOUT,
			function () use ($deferred, $sequenceNr): void {
				$deferred->reject(new Exceptions\LocalApiCall('Sending command to device failed'));

				unset($this->messagesListeners[$sequenceNr]);
				unset($this->messagesListenersTimers[$sequenceNr]);
			}
		);

		return $deferred->promise();
	}

	/**
	 * @param Array<string, int|float|string|bool> $states
	 *
	 * @return Promise\PromiseInterface
	 */
	public function writeStates(array $states): Promise\PromiseInterface
	{
		$deferred = new Promise\Deferred();

		$sequenceNr = $this->sendRequest(
			Types\LocalDeviceCommand::get(Types\LocalDeviceCommand::CMD_CONTROL),
			$states
		);

		$this->messagesListeners[$sequenceNr] = $deferred;

		$this->messagesListenersTimers[$sequenceNr] = $this->eventLoop->addTimer(
			self::WAIT_FOR_REPLY_TIMEOUT,
			function () use ($deferred, $sequenceNr): void {
				$deferred->reject(new Exceptions\LocalApiCall('Sending command to device failed'));

				unset($this->messagesListeners[$sequenceNr]);
				unset($this->messagesListenersTimers[$sequenceNr]);
			}
		);

		return $deferred->promise();
	}

	/**
	 * @param string $idx
	 * @param int|float|string|bool $value
	 *
	 * @return Promise\PromiseInterface
	 */
	public function writeState(string $idx, int|float|string|bool $value): Promise\PromiseInterface
	{
		return $this->writeStates([$idx => $value]);
	}

	/**
	 * @param Types\LocalDeviceCommand $command
	 * @param Array<string, int|float|string|bool>|null $data
	 * @param int|null $sequenceNr
	 *
	 * @return int
	 */
	private function sendRequest(
		Types\LocalDeviceCommand $command,
		?array $data = null,
		?int $sequenceNr = null
	): int {
		if ($sequenceNr === null) {
			$this->sequenceNr++;

			$payloadSequenceNr = $this->sequenceNr;

		} else {
			$payloadSequenceNr = $sequenceNr;
		}

		$payload = $this->buildPayload($payloadSequenceNr, $command, $data);

		$this->logger->debug(
			'Sending message to device',
			[
				'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'    => 'localapi-api',
				'device'  => [
					'identifier' => $this->identifier,
				],
				'message' => [
					'command'  => $command->getValue(),
					'data'     => $data,
					'sequence' => $sequenceNr,
				],
			]
		);

		$this->connection?->write((string) pack('C*', ...$payload));

		return $this->sequenceNr;
	}

	/**
	 * @param int $sequenceNr
	 * @param Types\LocalDeviceCommand $command
	 * @param Array<string, string|int|float|bool>|null $data
	 *
	 * @return int[]
	 */
	private function buildPayload(
		int $sequenceNr,
		Types\LocalDeviceCommand $command,
		?array $data = null
	): array {
		$header = [];

		if ($this->protocolVersion->equalsValue(Types\DeviceProtocolVersion::VERSION_V31)) {
			$payload = $this->generateData($command, $data);

			if ($payload === null) {
				throw new Exceptions\InvalidState('Payload could not be prepared');
			}

			if ($command->equalsValue(Types\LocalDeviceCommand::CMD_CONTROL)) {
				$payload = openssl_encrypt(
					$payload,
					'AES-128-ECB',
					mb_convert_encoding($this->localKey, 'ISO-8859-1', 'UTF-8'),
					OPENSSL_RAW_DATA
				);

				if ($payload === false) {
					throw new Exceptions\InvalidState('Payload could not be encrypted');
				}

				$payload = base64_encode($payload);

				$preMd5String = array_merge(
					(array) unpack('C*', 'data='),
					(array) unpack('C*', $payload),
					(array) unpack('C*', '||lpv='),
					(array) unpack('C*', '3.1||'),
					(array) unpack('C*', $this->localKey),
				);

				$hexDigest = md5(pack('C*', ...$preMd5String));
				$hexDigest = Nette\Utils\Strings::substring($hexDigest, 8);
				$hexDigest = Nette\Utils\Strings::substring($hexDigest, 0, 16);

				$header = array_merge(
					(array) unpack('C*', '3.1'),
					(array) unpack('C*', $hexDigest)
				);

				$payload = array_merge($header, (array) unpack('C*', $payload));

			} else {
				$payload = unpack('C*', $payload);
			}

			if ($payload === false) {
				throw new Exceptions\InvalidState('Payload could not be build');
			}

			return $this->stitchPayload(
				$sequenceNr,
				$payload,
				$command
			);

		} elseif ($this->protocolVersion->equalsValue(Types\DeviceProtocolVersion::VERSION_V33)) {
			if (!$command->equalsValue(Types\LocalDeviceCommand::CMD_DP_QUERY)) {
				$header = array_merge((array) unpack('C*', '3.3'), [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
			}

			$payload = $this->generateData($command, $data);

			if ($payload === null) {
				throw new Exceptions\InvalidState('Payload could not be prepared');
			}

			$payload = openssl_encrypt(
				$payload,
				'AES-128-ECB',
				mb_convert_encoding($this->localKey, 'ISO-8859-1', 'UTF-8'),
				OPENSSL_RAW_DATA
			);

			if ($payload === false) {
				throw new Exceptions\InvalidState('Payload could not be encrypted');
			}

			return $this->stitchPayload(
				$sequenceNr,
				array_merge($header, (array) unpack('C*', $payload)),
				$command
			);
		}

		throw new Exceptions\InvalidState(
			sprintf('Unknown protocol %s', strval($this->protocolVersion->getValue()))
		);
	}

	/**
	 * @param string $data
	 *
	 * @return Entities\API\DeviceRawMessage|null
	 */
	private function decodePayload(string $data): ?Entities\API\DeviceRawMessage
	{
		$buffer = unpack('C*', $data);

		if ($buffer !== false) {
			$bufferSize = count($buffer);

			$sequenceNr = (int) (($buffer[5] << 24) + ($buffer[6] << 16) + ($buffer[7] << 8) + $buffer[8]);
			$command = (int) (($buffer[9] << 24) + ($buffer[10] << 16) + ($buffer[11] << 8) + $buffer[12]);
			$size = (int) (($buffer[13] << 24) + ($buffer[14] << 16) + ($buffer[15] << 8) + $buffer[16]);
			$returnCode = (int) (($buffer[17] << 24) + ($buffer[18] << 16) + ($buffer[19] << 8) + $buffer[20]);
			$crc = (int) (($buffer[$bufferSize - 7] << 24) + ($buffer[$bufferSize - 6] << 16) + ($buffer[$bufferSize - 5] << 8) + $buffer[$bufferSize - 4]);

			$hasReturnCode = ($returnCode & 0xFFFFFF00) === 0;

			$bodyPart = array_slice($buffer, 0, $bufferSize - 8);

			$bodyPartPacked = pack('C*', ...$bodyPart);

			if (crc32($bodyPartPacked) !== $crc) {
				return null;
			}

			$payload = null;

			if ($this->protocolVersion->equalsValue(Types\DeviceProtocolVersion::VERSION_V31)) {
				$data = array_slice($buffer, 20, $bufferSize - 8);

				if ((($buffer[21] << 8) + $buffer[22]) === ord('{')) {
					$payload = pack('C*', ...$data);
				} elseif (
					$buffer[21] === ord('3')
					&& $buffer[22] === ord('.')
					&& $buffer[23] === ord('1')
				) {
					$this->logger->info(
						'Received message from device in version 3.1. This code is untested',
						[
							'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'   => 'localapi-api',
							'device' => [
								'identifier' => $this->identifier,
							],
						]
					);

					$data = array_slice($data, 3); // Remove version header

					// Remove (what I'm guessing, but not confirmed is) 16-bytes of MD5 hex digest of payload
					$data = array_slice($data, 16);

					$payload = openssl_decrypt(
						base64_decode(pack('C*', ...$data)),
						'AES-128-ECB',
						mb_convert_encoding($this->localKey, 'ISO-8859-1', 'UTF-8'),
						OPENSSL_RAW_DATA
					);

					if ($payload === false) {
						$this->logger->error(
							'Received message payload could not be decoded',
							[
								'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
								'type'    => 'localapi-api',
								'device'  => [
									'identifier' => $this->identifier,
								],
							]
						);

						return null;
					}
				}
			} elseif ($this->protocolVersion->equalsValue(Types\DeviceProtocolVersion::VERSION_V33)) {
				if ($size > 12) {
					$data = array_slice($buffer, 20, ($size + 8) - 20);

					if ($command === Types\LocalDeviceCommand::CMD_STATUS) {
						$data = array_slice($data, 15);
					}

					$payload = openssl_decrypt(
						pack('C*', ...$data),
						'AES-128-ECB',
						mb_convert_encoding($this->localKey, 'ISO-8859-1', 'UTF-8'),
						OPENSSL_RAW_DATA
					);

					if ($payload === false) {
						$this->logger->error(
							'Received message payload could not be decoded',
							[
								'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
								'type'    => 'localapi-api',
								'device'  => [
									'identifier' => $this->identifier,
								],
							]
						);

						return null;
					}
				}
			}

			$this->logger->debug(
				'Received message from device',
				[
					'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'    => 'localapi-api',
					'device'  => [
						'identifier' => $this->identifier,
					],
					'message' => [
						'command'    => $command,
						'data'       => $payload,
						'sequence'   => $sequenceNr,
						'returnCode' => $returnCode,
					],
				]
			);

			return new Entities\API\DeviceRawMessage(
				$this->identifier,
				Types\LocalDeviceCommand::get($command),
				$sequenceNr,
				$hasReturnCode ? $returnCode : null,
				$payload
			);
		}

		return null;
	}

	/**
	 * Fill the data structure for the command with the given values
	 *
	 * @param Types\LocalDeviceCommand $command
	 * @param Array<string, string|int|float|bool>|null $data
	 *
	 * @return string|null
	 */
	private function generateData(
		Types\LocalDeviceCommand $command,
		?array $data = null
	): ?string {
		$templates = [
			Types\LocalDeviceCommand::CMD_CONTROL      => [
				'devId' => '',
				'uid'   => '',
				't'     => '',
			],
			Types\LocalDeviceCommand::CMD_STATUS       => [
				'gwId'  => '',
				'devId' => '',
			],
			Types\LocalDeviceCommand::CMD_HEART_BEAT   => [],
			Types\LocalDeviceCommand::CMD_DP_QUERY     => [
				'gwId'  => '',
				'devId' => '',
				'uid'   => '',
				't'     => '',
			],
			Types\LocalDeviceCommand::CMD_CONTROL_NEW  => [
				'devId' => '',
				'uid'   => '',
				't'     => '',
			],
			Types\LocalDeviceCommand::CMD_DP_QUERY_NEW => [
				'devId' => '',
				'uid'   => '',
				't'     => '',
			],
		];

		$result = [];

		if (array_key_exists(intval($command->getValue()), $templates)) {
			$result = $templates[$command->getValue()];
		}

		if (array_key_exists('gwId', $result)) {
			$result['gwId'] = $this->gateway;
		}

		if (array_key_exists('devId', $result)) {
			$result['devId'] = $this->identifier;
		}

		if (array_key_exists('uid', $result)) {
			$result['uid'] = $this->identifier; // still use id, no separate uid
		}

		if (array_key_exists('t', $result)) {
			$result['t'] = (string) $this->dateTimeFactory->getNow()->getTimestamp();
		}

		if ($command->equalsValue(Types\LocalDeviceCommand::CMD_CONTROL_NEW)) {
			$result['dps'] = ['1' => '', '2' => '', '3' => ''];
		}

		if ($data !== null) {
			$result['dps'] = $data;
		}

		try {
			return $result === [] ? '{}' : Nette\Utils\Json::encode($result);

		} catch (Nette\Utils\JsonException $ex) {
			$this->logger->error(
				'Message payload could not be build',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'      => 'localapi-api',
					'device'    => [
						'identifier' => $this->identifier,
					],
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]
			);

			return null;
		}
	}

	/**
	 * Join the payload request parts together
	 *
	 * @param int $sequenceNr
	 * @param int[] $payload
	 * @param Types\LocalDeviceCommand $command
	 *
	 * @return int[]
	 */
	private function stitchPayload(
		int $sequenceNr,
		array $payload,
		Types\LocalDeviceCommand $command
	): array {
		$commandHb = [
			($command->getValue() >> 24) & 0xFF,
			($command->getValue() >> 16) & 0xFF,
			($command->getValue() >> 8) & 0xFF,
			($command->getValue() >> 0) & 0xFF,
		];

		$requestCntHb = [
			($sequenceNr >> 24) & 0xFF,
			($sequenceNr >> 16) & 0xFF,
			($sequenceNr >> 8) & 0xFF,
			($sequenceNr >> 0) & 0xFF,
		];

		$payloadHb = array_merge($payload, [0, 0, 0, 0, 0, 0, 170, 85]);

		$payloadHbLenHs = [
			(count($payloadHb) >> 24) & 0xFF,
			(count($payloadHb) >> 16) & 0xFF,
			(count($payloadHb) >> 8) & 0xFF,
			(count($payloadHb) >> 0) & 0xFF,
		];

		$headerHb = array_merge([0, 0, 85, 170], $requestCntHb, $commandHb, $payloadHbLenHs);
		$bufferHb = array_merge($headerHb, $payloadHb);

		// Calc the CRC of everything except where the CRC goes and the suffix
		$crc = crc32(pack('C*', ...array_slice($bufferHb, 0, count($bufferHb) - 8)));

		$crcHb = [
			($crc >> 24) & 0xFF,
			($crc >> 16) & 0xFF,
			($crc >> 8) & 0xFF,
			($crc >> 0) & 0xFF,
		];

		return array_merge(
			array_slice($bufferHb, 0, count($bufferHb) - 8),
			$crcHb,
			array_slice($bufferHb, count($bufferHb) - 4)
		);
	}

}
