<?php declare(strict_types = 1);

/**
 * DeviceSpecificationFunctionEntity.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          0.13.0
 *
 * @date           29.04.22
 */

namespace FastyBird\TuyaConnector\Entities\API;

use Nette;

/**
 * OpenAPI device specifications function entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceSpecificationFunctionEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var string */
	private string $code;

	/** @var string */
	private string $type;

	/** @var string */
	private string $values;

	/**
	 * @param string $code
	 * @param string $type
	 * @param string $values
	 */
	public function __construct(
		string $code,
		string $type,
		string $values
	) {
		$this->code = $code;
		$this->type = $type;
		$this->values = $values;
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
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getValues(): string
	{
		return $this->values;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'code'   => $this->getCode(),
			'type'   => $this->getType(),
			'values' => $this->getValues(),
		];
	}

}
