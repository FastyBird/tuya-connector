<?php declare(strict_types = 1);

/**
 * DiscoveredCloudDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           27.08.22
 */

namespace FastyBird\Connector\Tuya\Entities\Messages;

use Nette;
use Ramsey\Uuid;
use function array_map;

/**
 * Discovered cloud device entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DiscoveredCloudDevice implements Entity
{

	use Nette\SmartObject;

	/**
	 * @param array<DiscoveredCloudDataPoint> $dataPoints
	 */
	public function __construct(
		private readonly Uuid\UuidInterface $connector,
		private readonly string $id,
		private readonly string $localKey,
		private readonly string|null $ipAddress,
		private readonly string|null $name,
		private readonly string|null $model,
		private readonly string|null $icon,
		private readonly string|null $category,
		private readonly string|null $productId,
		private readonly string|null $productName,
		private readonly string|null $latitude,
		private readonly string|null $longitude,
		private readonly string|null $sn,
		private readonly string|null $mac,
		private readonly array $dataPoints,
	)
	{
	}

	public function getConnector(): Uuid\UuidInterface
	{
		return $this->connector;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getLocalKey(): string
	{
		return $this->localKey;
	}

	public function getIpAddress(): string|null
	{
		return $this->ipAddress;
	}

	public function getName(): string|null
	{
		return $this->name;
	}

	public function getModel(): string|null
	{
		return $this->model;
	}

	public function getIcon(): string|null
	{
		return $this->icon;
	}

	public function getCategory(): string|null
	{
		return $this->category;
	}

	public function getLatitude(): string|null
	{
		return $this->latitude;
	}

	public function getLongitude(): string|null
	{
		return $this->longitude;
	}

	public function getProductId(): string|null
	{
		return $this->productId;
	}

	public function getProductName(): string|null
	{
		return $this->productName;
	}

	public function getSn(): string|null
	{
		return $this->sn;
	}

	public function getMac(): string|null
	{
		return $this->mac;
	}

	/**
	 * @return array<DiscoveredCloudDataPoint>
	 */
	public function getDataPoints(): array
	{
		return $this->dataPoints;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'connector' => $this->getConnector()->toString(),
			'id' => $this->getId(),
			'local_key' => $this->getLocalKey(),
			'ip_address' => $this->getIpAddress(),
			'name' => $this->getName(),
			'model' => $this->getModel(),
			'icon' => $this->getIcon(),
			'category' => $this->getCategory(),
			'lat' => $this->getLatitude(),
			'lon' => $this->getLongitude(),
			'product_id' => $this->getProductId(),
			'product_name' => $this->getProductName(),
			'sn' => $this->getSn(),
			'mac' => $this->getMac(),
			'data_points' => array_map(
				static fn (DiscoveredCloudDataPoint $item): array => $item->toArray(),
				$this->getDataPoints(),
			),
		];
	}

}
