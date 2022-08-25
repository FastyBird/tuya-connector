<?php declare(strict_types = 1);

/**
 * ConnectorHelper.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Helpers
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Helpers;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\TuyaConnector\Types;
use Nette;
use Ramsey\Uuid;

/**
 * Useful connector helpers
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ConnectorHelper
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\DataStorage\IConnectorPropertiesRepository */
	private DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository;

	/**
	 * @param DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository
	) {
		$this->propertiesRepository = $propertiesRepository;
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param Types\ConnectorPropertyIdentifierType $type
	 *
	 * @return float|bool|int|string|null
	 */
	public function getConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifierType $type
	): float|bool|int|string|null {
		$configuration = $this->propertiesRepository->findByIdentifier($connectorId, strval($type->getValue()));

		if ($configuration instanceof MetadataEntities\Modules\DevicesModule\IConnectorStaticPropertyEntity) {
			if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE) {
				return Types\ClientModeType::isValidValue($configuration->getValue()) ? $configuration->getValue() : null;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_OPENAPI_ENDPOINT
				&& !Types\OpenApiEndpointType::isValidValue($configuration->getValue())
			) {
				return Types\OpenApiEndpointType::ENDPOINT_EUROPE;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_OPENPULSAR_ENDPOINT
				&& !Types\OpenPulsarEndpointType::isValidValue($configuration->getValue())
			) {
				return Types\OpenPulsarEndpointType::ENDPOINT_EUROPE;
			}

			return $configuration->getValue();
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_OPENAPI_ENDPOINT) {
			return Types\OpenApiEndpointType::ENDPOINT_EUROPE;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_OPENPULSAR_ENDPOINT) {
			return Types\OpenPulsarEndpointType::ENDPOINT_EUROPE;
		}

		return null;
	}

}
