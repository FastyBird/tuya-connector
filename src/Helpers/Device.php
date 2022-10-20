<?php declare(strict_types = 1);

/**
 * Device.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Helpers
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\Connector\Tuya\Helpers;

use FastyBird\Connector\Tuya\Types;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use Nette;
use Ramsey\Uuid;
use function is_bool;
use function is_string;
use function strval;

/**
 * Useful device helpers
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Device
{

	use Nette\SmartObject;

	public function __construct(
		private readonly DevicesModels\DataStorage\DevicePropertiesRepository $propertiesRepository,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function getConfiguration(
		Uuid\UuidInterface $deviceId,
		Types\DevicePropertyIdentifier $type,
	): float|bool|int|string|null
	{
		$configuration = $this->propertiesRepository->findByIdentifier($deviceId, strval($type->getValue()));

		if ($configuration instanceof MetadataEntities\DevicesModule\DeviceVariableProperty) {
			if ($type->getValue() === Types\DevicePropertyIdentifier::IDENTIFIER_IP_ADDRESS) {
				return is_string($configuration->getValue()) ? $configuration->getValue() : null;
			} elseif (
				$type->getValue() === Types\DevicePropertyIdentifier::IDENTIFIER_PROTOCOL_VERSION
				&& !Types\DeviceProtocolVersion::isValidValue($configuration->getValue())
			) {
				return Types\DeviceProtocolVersion::VERSION_V33;
			} elseif ($type->getValue() === Types\DevicePropertyIdentifier::IDENTIFIER_LOCAL_KEY) {
				return is_string($configuration->getValue()) ? $configuration->getValue() : null;
			} elseif ($type->getValue() === Types\DevicePropertyIdentifier::IDENTIFIER_USER_IDENTIFIER) {
				return is_string($configuration->getValue()) ? $configuration->getValue() : null;
			} elseif ($type->getValue() === Types\DevicePropertyIdentifier::IDENTIFIER_ENCRYPTED) {
				return is_bool($configuration->getValue()) ? $configuration->getValue() : false;
			}

			return $configuration->getValue();
		}

		if ($type->getValue() === Types\DevicePropertyIdentifier::IDENTIFIER_PROTOCOL_VERSION) {
			return Types\DeviceProtocolVersion::VERSION_V33;
		}

		return null;
	}

}
