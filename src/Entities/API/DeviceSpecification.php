<?php declare(strict_types = 1);

/**
 * DeviceSpecification.php
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
 * OpenAPI device specification entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceSpecification implements Entity
{

	use Nette\SmartObject;

	/** @var string */
	private string $category;

	/** @var DeviceSpecificationFunction[] */
	private array $functions;

	/** @var DeviceSpecificationStatus[] */
	private array $status;

	/**
	 * @param string $category
	 * @param DeviceSpecificationFunction[] $functions
	 * @param DeviceSpecificationStatus[] $status
	 */
	public function __construct(
		string $category,
		array $functions,
		array $status
	) {
		$this->category = $category;
		$this->functions = $functions;
		$this->status = $status;
	}

	/**
	 * @return string
	 */
	public function getCategory(): string
	{
		return $this->category;
	}

	/**
	 * @return DeviceSpecificationFunction[]
	 */
	public function getFunctions(): array
	{
		return $this->functions;
	}

	/**
	 * @return DeviceSpecificationStatus[]
	 */
	public function getStatus(): array
	{
		return $this->status;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'category'  => $this->getCategory(),
			'functions' => array_map(function (DeviceSpecificationFunction $item): array {
				return $item->toArray();
			}, $this->getFunctions()),
			'status'    => array_map(function (DeviceSpecificationStatus $item): array {
				return $item->toArray();
			}, $this->getStatus()),
		];
	}

}
