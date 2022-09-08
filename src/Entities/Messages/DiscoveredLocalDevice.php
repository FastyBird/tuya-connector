<?php declare(strict_types = 1);

/**
 * DiscoveredLocalDevice.php
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
use Ramsey\Uuid;

/**
 * Discovered local device entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DiscoveredLocalDevice implements Entity
{

	use Nette\SmartObject;

	/** @var Uuid\UuidInterface */
	private Uuid\UuidInterface $connector;

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

	/** @var DiscoveredLocalDataPoint[] */
	private array $dataPoints = [];

	/** @var Types\MessageSource */
	private Types\MessageSource $source;

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $id
	 * @param string $ipAddress
	 * @param string $productKey
	 * @param bool $encrypted
	 * @param string $version
	 * @param Types\MessageSource $source
	 */
	public function __construct(
		Uuid\UuidInterface $connector,
		string $id,
		string $ipAddress,
		string $productKey,
		bool $encrypted,
		string $version,
		Types\MessageSource $source
	) {
		$this->connector = $connector;
		$this->id = $id;
		$this->ipAddress = $ipAddress;
		$this->productKey = $productKey;
		$this->encrypted = $encrypted;
		$this->version = $version;

		$this->source = $source;
	}

	/**
	 * @return Uuid\UuidInterface
	 */
	public function getConnector(): Uuid\UuidInterface
	{
		return $this->connector;
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
	public function getSource(): Types\MessageSource
	{
		return $this->source;
	}

	/**
	 * @return DiscoveredLocalDataPoint[]
	 */
	public function getDataPoints(): array
	{
		return $this->dataPoints;
	}

	/**
	 * @param DiscoveredLocalDataPoint $dataPoint
	 *
	 * @return void
	 */
	public function addDatapoint(DiscoveredLocalDataPoint $dataPoint): void
	{
		$this->dataPoints[] = $dataPoint;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'connector'   => $this->getConnector()->toString(),
			'id'          => $this->id,
			'ip_address'  => $this->ipAddress,
			'product_key' => $this->productKey,
			'encrypted'   => $this->encrypted,
			'version'     => $this->version,
			'data_points' => array_map(function (DiscoveredLocalDataPoint $item): array {
				return $item->toArray();
			}, $this->getDataPoints()),
			'source'      => $this->getSource()->getValue(),
		];
	}

}
