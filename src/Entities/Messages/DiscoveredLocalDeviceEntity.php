<?php declare(strict_types = 1);

/**
 * DiscoveredLocalDeviceEntity.php
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

use FastyBird\TuyaConnector\Types;
use Nette;

/**
 * Discovered local device entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DiscoveredLocalDeviceEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var string */
	private string $id;

	/** @var string */
	private string $ipAddress;

	/** @var string */
	private string $productKey;

	/** @var bool */
	private bool $encrypted;

	/** @var string */
	private string $version;

	/** @var DiscoveredLocalDataPointEntity[] */
	private array $dataPoints = [];

	/** @var Types\MessageSourceType */
	private Types\MessageSourceType $source;

	/**
	 * @param string $id
	 * @param string $ipAddress
	 * @param string $productKey
	 * @param bool $encrypted
	 * @param string $version
	 * @param Types\MessageSourceType $source
	 */
	public function __construct(
		string $id,
		string $ipAddress,
		string $productKey,
		bool $encrypted,
		string $version,
		Types\MessageSourceType $source
	) {
		$this->id = $id;
		$this->ipAddress = $ipAddress;
		$this->productKey = $productKey;
		$this->encrypted = $encrypted;
		$this->version = $version;

		$this->source = $source;
	}

	/**
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getIpAddress(): string
	{
		return $this->ipAddress;
	}

	/**
	 * @return string
	 */
	public function getProductKey(): string
	{
		return $this->productKey;
	}

	/**
	 * @return bool
	 */
	public function isEncrypted(): bool
	{
		return $this->encrypted;
	}

	/**
	 * @return string
	 */
	public function getVersion(): string
	{
		return $this->version;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): Types\MessageSourceType
	{
		return $this->source;
	}

	/**
	 * @return DiscoveredLocalDataPointEntity[]
	 */
	public function getDataPoints(): array
	{
		return $this->dataPoints;
	}

	/**
	 * @param DiscoveredLocalDataPointEntity $dataPoint
	 *
	 * @return void
	 */
	public function addDatapoint(DiscoveredLocalDataPointEntity $dataPoint): void
	{
		$this->dataPoints[] = $dataPoint;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id'          => $this->id,
			'ip_address'  => $this->ipAddress,
			'product_key' => $this->productKey,
			'encrypted'   => $this->encrypted,
			'version'     => $this->version,
			'data_points' => array_map(function (DiscoveredLocalDataPointEntity $item): array {
				return $item->toArray();
			}, $this->getDataPoints()),
			'source'      => $this->getSource()->getValue(),
		];
	}

}
