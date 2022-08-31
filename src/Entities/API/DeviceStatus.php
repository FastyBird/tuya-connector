<?php declare(strict_types = 1);

/**
 * DeviceStatus.php
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
 * OpenAPI device detail status entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceStatus implements Entity
{

	use Nette\SmartObject;

	/** @var string */
	private string $code;

	/** @var string|int|float|bool */
	private string|int|float|bool $value;

	/**
	 * @param string $code
	 * @param string|int|float|bool $value
	 */
	public function __construct(
		string $code,
		string|int|float|bool $value
	) {
		$this->code = $code;
		$this->value = $value;
	}

	/**
	 * @return string
	 */
	public function getCode(): string
	{
		return $this->code;
	}

	/**
	 * @return bool|float|int|string
	 */
	public function getValue(): float|bool|int|string
	{
		return $this->value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'code'  => $this->getCode(),
			'value' => $this->getValue(),
		];
	}

}
