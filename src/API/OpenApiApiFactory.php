<?php declare(strict_types = 1);

/**
 * OpenApiApiFactory.php
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
final class OpenApiApiFactory
{

	/** @var OpenApiEntityFactory */
	private OpenApiEntityFactory $entityFactory;

	/** @var Helpers\ConnectorHelper */
	private Helpers\ConnectorHelper $connectorHelper;

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
	 * @param Helpers\ConnectorHelper $connectorHelper
	 * @param MetadataSchemas\IValidator $schemaValidator
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		OpenApiEntityFactory $entityFactory,
		Helpers\ConnectorHelper $connectorHelper,
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
	 * @return OpenApiApi
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): OpenApiApi
	{
		$endpoint = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifierType::get(Types\ConnectorPropertyIdentifierType::IDENTIFIER_OPENAPI_ENDPOINT)
		);

		if (!Types\OpenApiEndpointType::isValidValue($endpoint)) {
			throw new Exceptions\InvalidStateException('Configured OpenAPI endpoint is not valid');
		}

		$accessId = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifierType::get(Types\ConnectorPropertyIdentifierType::IDENTIFIER_ACCESS_ID)
		);

		$accessSecret = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifierType::get(Types\ConnectorPropertyIdentifierType::IDENTIFIER_ACCESS_SECRET)
		);

		return new OpenApiApi(
			strval($accessId),
			strval($accessSecret),
			'en',
			Types\OpenApiEndpointType::get($endpoint),
			$this->entityFactory,
			$this->schemaValidator,
			$this->dateTimeFactory,
			$this->eventLoop,
			$this->logger
		);
	}

}
