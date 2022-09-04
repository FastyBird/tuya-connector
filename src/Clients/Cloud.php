<?php declare(strict_types = 1);

/**
 * Cloud.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 * @since          0.13.0
 *
 * @date           25.08.22
 */

namespace FastyBird\TuyaConnector\Clients;

use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Nette;
use Nette\Utils;
use Psr\Log;
use Ratchet;
use Ratchet\RFC6455;
use React\EventLoop;
use React\Socket;
use Throwable;

/**
 * Cloud devices client
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Cloud implements Client
{

	use Nette\SmartObject;

	private const PING_INTERVAL = 30;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $pingTimer = null;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

	/** @var Ratchet\Client\WebSocket|null */
	private ?Ratchet\Client\WebSocket $wsConnection = null;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Helpers\Connector $connectorHelper
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Helpers\Connector $connectorHelper,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->connectorHelper = $connectorHelper;
		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		$secureContext = [
			'verify_peer'      => false,
			'verify_peer_name' => false,
			'check_hostname'   => false,
		];

		$reactConnector = new Socket\Connector([
			'dns'     => '8.8.8.8',
			'timeout' => 10,
			'tls'     => $secureContext,
		]);

		$connector = new Ratchet\Client\Connector($this->eventLoop, $reactConnector);
		$connector(
			$this->buildWsTopicUrl(),
			[],
			[
				'Connection' => 'Upgrade',
				'username'   => $this->connectorHelper->getConfiguration(
					$this->connector->getId(),
					Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
				),
				'password'   => $this->genPwd(),
			],
		)
			->then(function (Ratchet\Client\WebSocket $connection): void {
				$this->logger->debug(
					'Connected to Tuya sockets server',
					[
						'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'   => 'openapi-client',
					]
				);

				$this->wsConnection = $connection;

				$connection->on('message', function (RFC6455\Messaging\MessageInterface $message): void {
					$this->handleWsMessage($message->getPayload());
				});

				$connection->on('close', function ($code = null, $reason = null) {
					$this->logger->debug(
						'Connection to Tuya WS server was closed',
						[
							'source'     => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'       => 'openapi-client',
							'connection' => [
								'code'   => $code,
								'reason' => $reason,
							],
						]
					);
				});

				$this->pingTimer = $this->eventLoop->addPeriodicTimer(
					self::PING_INTERVAL,
					function () use ($connection): void {
						$connection->send(new RFC6455\Messaging\Frame(
							'',
							true,
							RFC6455\Messaging\Frame::OP_PING
						));
					}
				);
			})
			->otherwise(function (Throwable $ex): void {
				$this->logger->error(
					'Connection to Tuya WS server failed',
					[
						'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'      => 'openapi-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]
				);

				throw new DevicesModuleExceptions\TerminateException(
					'Connection to Tuya WS server failed',
					$ex->getCode(),
					$ex
				);
			});
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		$this->wsConnection?->close();

		if ($this->pingTimer !== null) {
			$this->eventLoop->cancelTimer($this->pingTimer);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConnected(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function discover(): void
	{
		// TODO: Implement discover() method.
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	private function handleWsMessage(string $message): void
	{
		try {
			$message = Utils\Json::decode($message);

		} catch (Utils\JsonException $ex) {
			$this->logger->error(
				'Could not decode received WS message',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'      => 'openapi-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]
			);

			return;
		}

		if (!is_object($message) || !property_exists($message, 'payload')) {
			$this->logger->error(
				'Received WS message is invalid',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'openapi-client',
				]
			);

			return;
		}

		$payload = base64_decode($message->payload, true);

		if ($payload === false) {
			$this->logger->error(
				'Received WS message payload could not be decoded',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'openapi-client',
				]
			);

			return;
		}

		$this->logger->debug(
			'Received message origin payload',
			[
				'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'    => 'openapi-client',
				'payload' => $payload,
			]
		);

		try {
			$payload = Utils\Json::decode($payload);

		} catch (Utils\JsonException $ex) {
			$this->logger->error(
				'Could not decode received WS message payload',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'      => 'openapi-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]
			);

			return;
		}

		if (!is_object($payload) || !property_exists($payload, 'data')) {
			$this->logger->error(
				'Received WS message payload is invalid',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'openapi-client',
				]
			);

			return;
		}

		$data = base64_decode($payload->data, true);

		if ($data === false) {
			$this->logger->error(
				'Received WS message payload data could not be decoded',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'openapi-client',
				]
			);

			return;
		}

		$accessSecret = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET)
		);

		$decodingKey = Utils\Strings::substring(strval($accessSecret), 8, 16);

		$decryptedData = openssl_decrypt(
			$data,
			'AES-128-ECB',
			$decodingKey,
			OPENSSL_RAW_DATA
		);

		if ($decryptedData === false) {
			$this->logger->error(
				'Received WS message payload data could not be decrypted',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'openapi-client',
				]
			);

			return;
		}

		$this->logger->debug(
			'Received message decrypted',
			[
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'openapi-client',
				'data'   => $decryptedData,
			]
		);

		try {
			$decryptedData = Utils\Json::decode($decryptedData);

		} catch (Utils\JsonException $ex) {
			$this->logger->error(
				'Could not decode received WS message payload data decrypted',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'      => 'openapi-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]
			);

			return;
		}

		if (
			!is_object($decryptedData)
			|| !property_exists($decryptedData, 'status')
			|| !is_array($decryptedData->status)
		) {
			$this->logger->error(
				'Received WS message payload is invalid',
				[
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'openapi-client',
				]
			);

			return;
		}

		foreach ($decryptedData->status as $status) {
			if (
				!is_object($status)
				|| !property_exists($status, 'code')
				|| !property_exists($status, 'value')
			) {
				continue;
			}
		}
	}

	/**
	 * @return string
	 */
	private function buildWsTopicUrl(): string
	{
		return $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_OPENPULSAR_ENDPOINT)
		) . 'ws/v2/consumer/persistent/' . $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
		) . '/out/' . $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_OPENPULSAR_TOPIC)
		) . '/' . $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
		) . '-sub?ackTimeoutMillis=3000&subscriptionType=Failover';
	}

	/**
	 * @return string
	 */
	private function genPwd(): string
	{
		$passString = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
		) . md5(
			strval($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET)
			))
		);

		return Utils\Strings::substring(md5($passString), 8, 16);
	}

}
