<?php declare(strict_types = 1);

/**
 * CloudFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 * @since          0.13.0
 *
 * @date           27.08.22
 */

namespace FastyBird\TuyaConnector\API;

use FastyBird\DateTimeFactory;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Schemas as MetadataSchemas;
use FastyBird\TuyaConnector\Exceptions;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Psr\Log;
use React\EventLoop;

/**
 * OpenAPI API factory
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class OpenApiFactory
{

	/** @var EntityFactory */
	private EntityFactory $entityFactory;

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
	 * @param EntityFactory $entityFactory
	 * @param Helpers\Connector $connectorHelper
	 * @param MetadataSchemas\IValidator $schemaValidator
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		EntityFactory $entityFactory,
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
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return OpenApi
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): OpenApi
	{
		$endpoint = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_OPENAPI_ENDPOINT)
		);

		if (!Types\OpenApiEndpoint::isValidValue($endpoint)) {
			throw new Exceptions\InvalidState('Configured OpenAPI endpoint is not valid');
		}

		$accessId = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID)
		);

		$accessSecret = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET)
		);

		return new OpenApi(
			strval($accessId),
			strval($accessSecret),
			'en',
			Types\OpenApiEndpoint::get($endpoint),
			$this->entityFactory,
			$this->schemaValidator,
			$this->dateTimeFactory,
			$this->eventLoop,
			$this->logger
		);
	}

}
