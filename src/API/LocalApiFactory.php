<?php declare(strict_types = 1);

/**
 * LocalApiFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 * @since          0.13.0
 *
 * @date           05.09.22
 */

namespace FastyBird\TuyaConnector\API;

use FastyBird\DateTimeFactory;
use FastyBird\Metadata\Schemas as MetadataSchemas;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Psr\Log;
use React\EventLoop;

/**
 * Local device API factory
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class LocalApiFactory
{

	/** @var OpenApiEntityFactory */
	private OpenApiEntityFactory $entityFactory;

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

	/** @var MetadataSchemas\IValidator */
	private MetadataSchemas\IValidator $schemaValidator;

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param OpenApiEntityFactory $entityFactory
	 * @param Helpers\Connector $connectorHelper
	 * @param MetadataSchemas\IValidator $schemaValidator
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		OpenApiEntityFactory $entityFactory,
		Helpers\Connector $connectorHelper,
		MetadataSchemas\IValidator $schemaValidator,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->entityFactory = $entityFactory;
		$this->connectorHelper = $connectorHelper;
		$this->schemaValidator = $schemaValidator;
		$this->dateTimeFactory = $dateTimeFactory;
		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @param string $identifier
	 * @param string|null $gateway
	 * @param string $localKey
	 * @param string $ipAddress
	 * @param Types\DeviceProtocolVersion $protocolVersion
	 *
	 * @return LocalApi
	 */
	public function create(
		string $identifier,
		?string $gateway,
		string $localKey,
		string $ipAddress,
		Types\DeviceProtocolVersion $protocolVersion
	): LocalApi {
		return new LocalApi(
			$identifier,
			$gateway,
			$localKey,
			$ipAddress,
			$protocolVersion,
			$this->dateTimeFactory,
			$this->eventLoop,
			$this->logger
		);
	}

}
