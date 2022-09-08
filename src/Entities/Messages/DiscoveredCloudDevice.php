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

use FastyBird\TuyaConnector\Types;
use Nette;
use Ramsey\Uuid;

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

	/** @var Uuid\UuidInterface */
	private Uuid\UuidInterface $connector;

	/** @var string */
	private string $id;

	/** @var string */
	private string $localKey;

	/** @var string|null */
	private ?string $ipAddress;

	/** @var string|null */
	private ?string $name;

	/** @var string|null */
	private ?string $model;

	/** @var string|null */
	private ?string $sn;

	/** @var string|null */
	private ?string $mac;

	/** @var DiscoveredCloudDataPoint[] */
	private array $dataPoints;

	/** @var Types\MessageSource */
	private Types\MessageSource $source;

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $id
	 * @param string $localKey
	 * @param string|null $ipAddress
	 * @param string|null $name
	 * @param string|null $model
	 * @param string|null $sn
	 * @param string|null $mac
	 * @param DiscoveredCloudDataPoint[] $dataPoints
	 * @param Types\MessageSource $source
	 */
	public function __construct(
		Uuid\UuidInterface $connector,
		string $id,
		string $localKey,
		?string $ipAddress,
		?string $name,
		?string $model,
		?string $sn,
		?string $mac,
		array $dataPoints,
		Types\MessageSource $source
	) {
		$this->connector = $connector;
		$this->id = $id;
		$this->localKey = $localKey;
		$this->ipAddress = $ipAddress;
		$this->name = $name;
		$this->model = $model;
		$this->sn = $sn;
		$this->mac = $mac;
		$this->dataPoints = $dataPoints;
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
	public function getLocalKey(): string
	{
		return $this->localKey;
	}

	/**
	 * @return string|null
	 */
	public function getIpAddress(): ?string
	{
		return $this->ipAddress;
	}

	/**
	 * @return string|null
	 */
	public function getName(): ?string
	{
		return $this->name;
	}

	/**
	 * @return string|null
	 */
	public function getModel(): ?string
	{
		return $this->model;
	}

	/**
	 * @return string|null
	 */
	public function getSn(): ?string
	{
		return $this->sn;
	}

	/**
	 * @return string|null
	 */
	public function getMac(): ?string
	{
		return $this->mac;
	}

	/**
	 * @return DiscoveredCloudDataPoint[]
	 */
	public function getDataPoints(): array
	{
		return $this->dataPoints;
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
			'connector'   => $this->getConnector()->toString(),
			'id'          => $this->getId(),
			'local_key'   => $this->getLocalKey(),
			'ip_address'  => $this->getIpAddress(),
			'name'        => $this->getName(),
			'model'       => $this->getModel(),
			'sn'          => $this->getSn(),
			'mac'         => $this->getMac(),
			'data_points' => array_map(function (DiscoveredCloudDataPoint $item): array {
				return $item->toArray();
			}, $this->getDataPoints()),
			'source'      => $this->getSource()->getValue(),
		];
	}

}
