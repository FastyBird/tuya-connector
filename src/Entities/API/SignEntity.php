<?php declare(strict_types = 1);

/**
 * SignEntity.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          0.13.0
 *
 * @date           26.04.22
 */

namespace FastyBird\TuyaConnector\Entities\API;

use Nette;

/**
 * OpenAPI sign entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SignEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var string */
	private string $sign;

	/** @var int */
	private int $timestamp;

	/**
	 * @param string $sign
	 * @param int $timestamp
	 */
	public function __construct(
		string $sign,
		int $timestamp
	) {
		$this->sign = $sign;
		$this->timestamp = $timestamp;
	}

	/**
	 * @return string
	 */
	public function getSign(): string
	{
		return $this->sign;
	}

	/**
	 * @return int
	 */
	public function getTimestamp(): int
	{
		return $this->timestamp;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'sign'      => $this->getSign(),
			'timestamp' => $this->getTimestamp(),
		];
	}

}
