<?php declare(strict_types = 1);

/**
 * LocalDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     ValueObjects
 * @since          1.0.0
 *
 * @date           28.08.24
 */

namespace FastyBird\Connector\Tuya\ValueObjects;

use Orisai\ObjectMapper;

/**
 * Local device info
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     ValueObjects
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final readonly class LocalDevice implements ObjectMapper\MappedObject
{

	public function __construct(
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $id,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		#[ObjectMapper\Modifiers\FieldName('ip_address')]
		private string $ipAddress,
		#[ObjectMapper\Rules\BoolValue(castBoolLike: true)]
		private bool $encrypted,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $version,
	)
	{
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getIpAddress(): string
	{
		return $this->ipAddress;
	}

	public function isEncrypted(): bool
	{
		return $this->encrypted;
	}

	public function getVersion(): string
	{
		return $this->version;
	}

}
