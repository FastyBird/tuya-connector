<?php declare(strict_types = 1);

/**
 * OpenApi.php
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

use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Nette;
use Nette\Utils;
use Psr\Log;
use Ratchet;
use Ratchet\RFC6455;
use React\EventLoop;
use Throwable;

/**
 * Cloud OpenAPI devices client
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class OpenApi implements Client
{

	use Nette\SmartObject;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

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
		$connector = new Ratchet\Client\Connector($this->eventLoop);
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
				$connection->on('message', function(RFC6455\Messaging\MessageInterface $msg): void {
					echo "Received: {$msg}\n";
				});

				$connection->on('close', function($code = null, $reason = null) {
					echo "Connection closed ({$code} - {$reason})\n";
				});

				var_dump('Connection established');
			})
			->otherwise(function (Throwable $ex): void {
				var_dump($ex->getMessage());
			});
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		// TODO: Implement disconnect() method.
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
			) . '-sub' . '?ackTimeoutMillis=3000&subscriptionType=Failover';
	}

	/**
	 * @return string
	 */
	private function genPwd(): string
	{
		$mixString = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
		) . md5(
			$this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET)
			)
		);

		return md5(Utils\Strings::substring($mixString, 8, 24));
	}

}
