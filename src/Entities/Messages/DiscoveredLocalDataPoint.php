<?php declare(strict_types = 1);

/**
 * DiscoveredLocalDataPoint.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          0.13.0
 *
 * @date           29.08.22
 */

namespace FastyBird\TuyaConnector\Entities\Messages;

use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\TuyaConnector\Types;
use Nette;

/**
 * Discovered cloud device data point entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DiscoveredLocalDataPoint implements Entity
{

	use Nette\SmartObject;

	/** @var string */
	private string $device;

	/** @var string */
	private string $code;

	/** @var string */
	private string $name;

	/** @var MetadataTypes\DataTypeType */
	private MetadataTypes\DataTypeType $dataType;

	/** @var string|null */
	private ?string $unit;

	/** @var string|null */
	private ?string $format;

	/** @var int|float|null */
	private int|float|null $min;

	/** @var int|float|null */
	private int|float|null $max;

	/** @var int|float|null */
	private int|float|null $step;

	/** @var bool */
	private bool $queryable;

	/** @var bool */
	private bool $settable;

	/** @var Types\MessageSource */
	private Types\MessageSource $source;

	public function __construct(
		string $device,
		string $code,
		string $name,
		MetadataTypes\DataTypeType $dataType,
		?string $unit,
		?string $format,
		int|float|null $min,
		int|float|null $max,
		int|float|null $step,
		bool $queryable,
		bool $settable,
		Types\MessageSource $source
	) {
		$this->device = $device;

		$this->code = $code;
		$this->name = $name;
		$this->dataType = $dataType;
		$this->unit = $unit;
		$this->format = $format;
		$this->min = $min;
		$this->max = $max;
		$this->step = $step;
		$this->queryable = $queryable;
		$this->settable = $settable;
		$this->source = $source;
	}

	/**
	 * @return string
	 */
	public function getDevice(): string
	{
		return $this->device;
	}

	/**
	 * @return string
	 */
	public function getCode(): string
	{
		return $this->code;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return MetadataTypes\DataTypeType
	 */
	public function getDataType(): MetadataTypes\DataTypeType
	{
		return $this->dataType;
	}

	/**
	 * @return string|null
	 */
	public function getUnit(): ?string
	{
		return $this->unit;
	}

	/**
	 * @return string|null
	 */
	public function getFormat(): ?string
	{
		return $this->format;
	}

	/**
	 * @return float|int|null
	 */
	public function getMin(): float|int|null
	{
		return $this->min;
	}

	/**
	 * @return float|int|null
	 */
	public function getMax(): float|int|null
	{
		return $this->max;
	}

	/**
	 * @return float|int|null
	 */
	public function getStep(): float|int|null
	{
		return $this->step;
	}

	/**
	 * @return bool
	 */
	public function isQueryable(): bool
	{
		return $this->queryable;
	}

	/**
	 * @return bool
	 */
	public function isSettable(): bool
	{
		return $this->settable;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): Types\MessageSource
	{
		return $this->source;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'device' => $this->getDevice(),

			'code'      => $this->getCode(),
			'name'      => $this->getName(),
			'data_type' => $this->getDataType()->getValue(),
			'unit'      => $this->getUnit(),
			'format'    => $this->getFormat(),
			'min'       => $this->getMin(),
			'max'       => $this->getMax(),
			'step'      => $this->getStep(),
			'queryable' => $this->isQueryable(),
			'settable'  => $this->isSettable(),
			'source'    => $this->getSource()->getValue(),
		];
	}

}
