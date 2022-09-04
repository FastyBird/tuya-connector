<?php declare(strict_types = 1);

/**
 * DataPointStatus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Properties
 * @since          0.13.0
 *
 * @date           04.09.22
 */

namespace FastyBird\TuyaConnector\Entities\Messages;

use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\TuyaConnector\Types;
use Nette;

/**
 * Data point status entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DataPointStatus implements Entity
{

	use Nette\SmartObject;

	/** @var Types\MessageSource */
	private Types\MessageSource $source;

	/** @var string */
	private string $identifier;

	/** @var float|int|string|bool|MetadataTypes\SwitchPayloadType|null */
	private float|int|string|bool|MetadataTypes\SwitchPayloadType|null $value;

	/**
	 * @param Types\MessageSource $source
	 * @param string $identifier
	 * @param float|int|string|bool|MetadataTypes\SwitchPayloadType|null $value
	 */
	public function __construct(
		Types\MessageSource $source,
		string $identifier,
		float|int|string|bool|MetadataTypes\SwitchPayloadType|null $value
	) {
		$this->source = $source;
		$this->identifier = $identifier;
		$this->value = $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): Types\MessageSource
	{
		return $this->source;
	}

	/**
	 * @return string
	 */
	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	/**
	 * @return float|int|string|bool|MetadataTypes\SwitchPayloadType|null
	 */
	public function getValue(): float|int|string|bool|MetadataTypes\SwitchPayloadType|null
	{
		return $this->value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'source'     => $this->getSource()->getValue(),
			'identifier' => $this->getIdentifier(),
			'value'      => is_scalar($this->getValue()) ? $this->getValue() : strval($this->getValue()),
		];
	}

}
