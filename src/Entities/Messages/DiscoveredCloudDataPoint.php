<?php declare(strict_types = 1);

/**
 * DiscoveredCloudDevice.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          0.13.0
 *
 * @date           27.08.22
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
final class DiscoveredCloudDataPoint implements Entity
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

	/** @var string[] */
	private array $range;

	/** @var int|float|null */
	private int|float|null $min;

	/** @var int|float|null */
	private int|float|null $max;

	/** @var int|float|null */
	private int|float|null $step;

	/** @var int|float|null */
	private int|float|null $scale;

	/** @var bool */
	private bool $queryable;

	/** @var bool */
	private bool $settable;

	/** @var Types\MessageSource */
	private Types\MessageSource $source;

	/**
	 * @param string $device
	 * @param string $code
	 * @param string $name
	 * @param MetadataTypes\DataTypeType $dataType
	 * @param string|null $unit
	 * @param string[] $range
	 * @param int|float|null $min
	 * @param int|float|null $max
	 * @param int|float|null $step
	 * @param int|float|null $scale
	 * @param bool $queryable
	 * @param bool $settable
	 * @param Types\MessageSource $source
	 */
	public function __construct(
		string $device,
		string $code,
		string $name,
		MetadataTypes\DataTypeType $dataType,
		?string $unit,
		array $range,
		int|float|null $min,
		int|float|null $max,
		int|float|null $step,
		int|float|null $scale,
		bool $queryable,
		bool $settable,
		Types\MessageSource $source
	) {
		$this->device = $device;

		$this->code = $code;
		$this->name = $name;
		$this->dataType = $dataType;
		$this->unit = $unit;
		$this->range = $range;
		$this->min = $min;
		$this->max = $max;
		$this->step = $step;
		$this->scale = $scale;
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
	 * @return string[]
	 */
	public function getRange(): array
	{
		return $this->range;
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
	 * @return float|int|null
	 */
	public function getScale(): float|int|null
	{
		return $this->scale;
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
	 * @return Array<int, Array<int, string|int|float|null>>|string[]|null
	 */
	public function getFormat(): ?array
	{
		if (
			(
				$this->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_CHAR)
				|| $this->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UCHAR)
				|| $this->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SHORT)
				|| $this->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_USHORT)
				|| $this->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_INT)
				|| $this->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UINT)
				|| $this->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_FLOAT)
			) && (
				$this->getMin() !== null
				|| $this->getMax() !== null
			)
		) {
			return [
				[
					MetadataTypes\DataTypeShortType::DATA_TYPE_FLOAT,
					$this->getMin(),
				],
				[
					MetadataTypes\DataTypeShortType::DATA_TYPE_FLOAT,
					$this->getMax(),
				],
			];

		} elseif (
			$this->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)
			&& $this->getRange() !== []
		) {
			return $this->getRange();
		}

		return null;
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
			'range'     => $this->getRange(),
			'min'       => $this->getMin(),
			'max'       => $this->getMax(),
			'step'      => $this->getStep(),
			'scale'     => $this->getScale(),
			'queryable' => $this->isQueryable(),
			'settable'  => $this->isSettable(),
			'source'    => $this->getSource()->getValue(),
		];
	}

}
