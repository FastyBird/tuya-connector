<?php declare(strict_types = 1);

/**
 * UserDeviceSpecificationsEntity.php
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
 * OpenAPI device specifications entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class UserDeviceSpecificationsEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var string */
	private string $category;

	/** @var UserDeviceSpecificationsFunctionEntity[] */
	private array $functions;

	/** @var UserDeviceSpecificationsStatusEntity[] */
	private array $status;

	/**
	 * @param string $category
	 * @param UserDeviceSpecificationsFunctionEntity[] $functions
	 * @param UserDeviceSpecificationsStatusEntity[] $status
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
	 * @return UserDeviceSpecificationsFunctionEntity[]
	 */
	public function getFunctions(): array
	{
		return $this->functions;
	}

	/**
	 * @return UserDeviceSpecificationsStatusEntity[]
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
			'functions' => array_map(function (UserDeviceSpecificationsFunctionEntity $item): array {
				return $item->toArray();
			}, $this->getFunctions()),
			'status'    => array_map(function (UserDeviceSpecificationsStatusEntity $item): array {
				return $item->toArray();
			}, $this->getStatus()),
		];
	}

}
