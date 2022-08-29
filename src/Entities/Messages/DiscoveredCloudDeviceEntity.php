<?php declare(strict_types = 1);

/**
 * DiscoveredCloudDeviceEntity.php
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
 * Discovered cloud device entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DiscoveredCloudDeviceEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var string */
	private string $id;

	/** @var string */
	private string $localKey;

	/** @var string */
	private string $name;

	/** @var string */
	private string $uid;

	/** @var string */
	private string $model;

	/** @var string|null */
	private ?string $sn;

	/** @var string|null */
	private ?string $mac;

	/** @var DiscoveredCloudDataPointEntity[] */
	private array $dataPoints;

	/** @var Types\MessageSourceType */
	private Types\MessageSourceType $source;

	/**
	 * @param string $id
	 * @param string $localKey
	 * @param string $name
	 * @param string $uid
	 * @param string $model
	 * @param string|null $sn
	 * @param string|null $mac
	 * @param DiscoveredCloudDataPointEntity[] $dataPoints
	 * @param Types\MessageSourceType $source
	 */
	public function __construct(
		string $id,
		string $localKey,
		string $name,
		string $uid,
		string $model,
		?string $sn,
		?string $mac,
		array $dataPoints,
		Types\MessageSourceType $source
	) {
		$this->id = $id;
		$this->localKey = $localKey;
		$this->name = $name;
		$this->uid = $uid;
		$this->model = $model;
		$this->sn = $sn;
		$this->mac = $mac;
		$this->dataPoints = $dataPoints;
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
	public function getLocalKey(): string
	{
		return $this->localKey;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getUid(): string
	{
		return $this->uid;
	}

	/**
	 * @return string
	 */
	public function getModel(): string
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
	 * @return DiscoveredCloudDataPointEntity[]
	 */
	public function getDataPoints(): array
	{
		return $this->dataPoints;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): Types\MessageSourceType
	{
		return $this->source;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id'          => $this->getId(),
			'local_key'   => $this->getLocalKey(),
			'name'        => $this->getName(),
			'uid'         => $this->getUid(),
			'model'       => $this->getModel(),
			'sn'          => $this->getSn(),
			'mac'         => $this->getMac(),
			'data_points' => array_map(function (DiscoveredCloudDataPointEntity $item): array {
				return $item->toArray();
			}, $this->getDataPoints()),
			'source'      => $this->getSource()->getValue(),
		];
	}

}
