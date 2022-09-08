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

use FastyBird\DateTimeFactory;
use FastyBird\Metadata;
use FastyBird\TuyaConnector\Exceptions;
use FastyBird\TuyaConnector\Types;
use Nette;
use Psr\Log;
use React\EventLoop;
use React\Socket;
use Throwable;
use function React\Async\await;

/**
 * Local UDP device interface
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class LocalApi
{

	use Nette\SmartObject;

	private const SOCKET_PORT = 6668;

	/** @var string */
	private string $identifier;

	/** @var string */
	private string $gateway;

	/** @var string */
	private string $localKey;

	/** @var string */
	private string $ipAddress;

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
	 * @return void
	 */
	public function connect(): void
	{
		try {
			$connector = new Socket\Connector($this->eventLoop);

			/** @var Socket\ConnectionInterface $connection */
			$connection = await($connector->connect($this->ipAddress . ':' . self::SOCKET_PORT));

			$this->connection = $connection;

			$this->connection->on('data', function ($chunk) {
				var_dump('RECEIVED DATA');
				var_dump($chunk);
				$buffer = unpack('C*', $chunk);

				if ($buffer !== false) {
					$bufferSize = count($buffer);

					$seq = ($buffer[5] << 24) + ($buffer[6] << 16) + ($buffer[7] << 8) + $buffer[8];
					$cmd = ($buffer[9] << 24) + ($buffer[10] << 16) + ($buffer[11] << 8) + $buffer[12];
					$size = ($buffer[13] << 24) + ($buffer[14] << 16) + ($buffer[15] << 8) + $buffer[16];
					$returnCode = ($buffer[17] << 24) + ($buffer[18] << 16) + ($buffer[19] << 8) + $buffer[20];
					$crc = ($buffer[$bufferSize - 7] << 24) + ($buffer[$bufferSize - 6] << 16) + ($buffer[$bufferSize - 5] << 8) + $buffer[$bufferSize - 4];

					$hasReturnCode = ($returnCode & 0xFFFFFF00) === 0;

					$bodyPart = array_slice($buffer, 0, $bufferSize - 8);

					$bodyPartPacked = pack('C*', ...$bodyPart);

					if (crc32($bodyPartPacked) !== $crc) {
						return;
					}

					$payload = '';

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
						}
					} elseif ($this->protocolVersion->equalsValue(Types\DeviceProtocolVersion::VERSION_V33)) {
						if ($size > 12) {
							$data = array_slice($buffer, 20, ($size + 8) - 20);

							if ($cmd === Types\LocalDeviceCommand::CMD_STATUS) {
								$data = array_slice($data, 15);
							}

							$payload = openssl_decrypt(
								pack('C*', ...$data),
								'AES-128-ECB',
								mb_convert_encoding($this->localKey, 'ISO-8859-1', 'UTF-8'),
								OPENSSL_RAW_DATA
							);
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
								'seq'     => $seq,
								'cmd'     => $cmd,
								'rc'      => $returnCode,
								'payload' => $payload,
							],
						]
					);

					var_dump($payload);
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
		}
	}

	/**
	 * @return void
	 */
	public function disconnect(): void
	{
		$this->connection?->close();
	}

	/**
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return $this->connection !== null;
	}

	public function readStates()
	{
	}

	public function writeStates(): bool
	{
		return false;
	}

	public function writeState(): bool
	{
		return false;
	}

	/**
	 * @param int $sequenceNr
	 * @param Types\LocalDeviceCommand $command
	 * @param Array<string, string|int|float|bool>|null $data
	 *
	 * @return int[]
	 *
	 * @throws Nette\Utils\JsonException
	 */
	private function buildPayload(
		int $sequenceNr,
		Types\LocalDeviceCommand $command,
		?array $data = null
	): array {
		$header = [];

		if ($this->protocolVersion->equalsValue(Types\DeviceProtocolVersion::VERSION_V31)) {
			$payload = $this->generateData($command, $data);

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

			$payload = openssl_encrypt(
				$this->generateData($command, $data),
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
	 * Fill the data structure for the command with the given values
	 *
	 * @param Types\LocalDeviceCommand $command
	 * @param Array<string, string|int|float|bool>|null $data
	 *
	 * @return string
	 * @throws Nette\Utils\JsonException
	 */
	private function generateData(
		Types\LocalDeviceCommand $command,
		?array $data = null
	): string {
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

		return $result === [] ? '{}' : Nette\Utils\Json::encode($result);
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
