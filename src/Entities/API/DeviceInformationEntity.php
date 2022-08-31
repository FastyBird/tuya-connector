<?php declare(strict_types = 1);

/**
 * DeviceInformationEntity.php
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
 * OpenAPI device information entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceInformationEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var string */
	private string $id;

	/** @var string|null */
	private ?string $gatewayId;

	/** @var string|null */
	private ?string $nodeId;

	/** @var string|null */
	private ?string $uuid;

	/** @var string|null */
	private ?string $category;

	/** @var string|null */
	private ?string $categoryName;

	/** @var string|null */
	private ?string $name;

	/** @var string|null */
	private ?string $productId;

	/** @var string|null */
	private ?string $productName;

	/** @var string */
	private string $localKey;

	/** @var bool */
	private bool $sub;

	/** @var string|null */
	private ?string $assetId;

	/** @var string|null */
	private ?string $ownerId;

	/** @var string|null */
	private ?string $ip;

	/** @var string|null */
	private ?string $lon;

	/** @var string|null */
	private ?string $lat;

	/** @var string|null */
	private ?string $model;

	/** @var string|null */
	private ?string $timeZone;

	/** @var int|null */
	private ?int $activeTime;

	/** @var int|null */
	private ?int $createTime;

	/** @var int|null */
	private ?int $updateTime;

	/** @var bool */
	private bool $online;

	/** @var string|null */
	private ?string $icon;

	/**
	 * @param string $id
	 * @param string|null $gatewayId
	 * @param string|null $nodeId
	 * @param string|null $uuid
	 * @param string|null $category
	 * @param string|null $categoryName
	 * @param string|null $name
	 * @param string|null $productId
	 * @param string|null $productName
	 * @param string $localKey
	 * @param bool $sub
	 * @param string|null $assetId
	 * @param string|null $ownerId
	 * @param string|null $ip
	 * @param string|null $lon
	 * @param string|null $lat
	 * @param string|null $model
	 * @param string|null $timeZone
	 * @param int|null $activeTime
	 * @param int|null $createTime
	 * @param int|null $updateTime
	 * @param bool $online
	 * @param string|null $icon
	 */
	public function __construct(
		string $id,
		?string $gatewayId,
		?string $nodeId,
		?string $uuid,
		?string $category,
		?string $categoryName,
		?string $name,
		?string $productId,
		?string $productName,
		string $localKey,
		bool $sub,
		?string $assetId,
		?string $ownerId,
		?string $ip,
		?string $lon,
		?string $lat,
		?string $model,
		?string $timeZone,
		?int $activeTime,
		?int $createTime,
		?int $updateTime,
		bool $online,
		?string $icon
	) {
		$this->id = $id;
		$this->gatewayId = $gatewayId;
		$this->nodeId = $nodeId;
		$this->uuid = $uuid;
		$this->category = $category;
		$this->categoryName = $categoryName;
		$this->name = $name;
		$this->productId = $productId;
		$this->productName = $productName;
		$this->localKey = $localKey;
		$this->sub = $sub;
		$this->assetId = $assetId;
		$this->ownerId = $ownerId;
		$this->ip = $ip;
		$this->lon = $lon;
		$this->lat = $lat;
		$this->model = $model;
		$this->timeZone = $timeZone;
		$this->activeTime = $activeTime;
		$this->createTime = $createTime;
		$this->updateTime = $updateTime;
		$this->online = $online;
		$this->icon = $icon;
	}

	/**
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * @return string|null
	 */
	public function getGatewayId(): ?string
	{
		return $this->gatewayId;
	}

	/**
	 * @return string|null
	 */
	public function getNodeId(): ?string
	{
		return $this->nodeId;
	}

	/**
	 * @return string|null
	 */
	public function getUuid(): ?string
	{
		return $this->uuid;
	}

	/**
	 * @return string|null
	 */
	public function getCategory(): ?string
	{
		return $this->category;
	}

	/**
	 * @return string|null
	 */
	public function getCategoryName(): ?string
	{
		return $this->categoryName;
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
	public function getProductId(): ?string
	{
		return $this->productId;
	}

	/**
	 * @return string|null
	 */
	public function getProductName(): ?string
	{
		return $this->productName;
	}

	/**
	 * @return string
	 */
	public function getLocalKey(): string
	{
		return $this->localKey;
	}

	/**
	 * @return bool
	 */
	public function getSub(): bool
	{
		return $this->sub;
	}

	/**
	 * @return string|null
	 */
	public function getAssetId(): ?string
	{
		return $this->assetId;
	}

	/**
	 * @return string|null
	 */
	public function getOwnerId(): ?string
	{
		return $this->ownerId;
	}

	/**
	 * @return string|null
	 */
	public function getIp(): ?string
	{
		return $this->ip;
	}

	/**
	 * @return string|null
	 */
	public function getLon(): ?string
	{
		return $this->lon;
	}

	/**
	 * @return string|null
	 */
	public function getLat(): ?string
	{
		return $this->lat;
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
	public function getTimeZone(): ?string
	{
		return $this->timeZone;
	}

	/**
	 * @return int|null
	 */
	public function getActiveTime(): ?int
	{
		return $this->activeTime;
	}

	/**
	 * @return int|null
	 */
	public function getCreateTime(): ?int
	{
		return $this->createTime;
	}

	/**
	 * @return int|null
	 */
	public function getUpdateTime(): ?int
	{
		return $this->updateTime;
	}

	/**
	 * @return bool
	 */
	public function isOnline(): bool
	{
		return $this->online;
	}

	/**
	 * @return string|null
	 */
	public function getIcon(): ?string
	{
		return $this->icon;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id'            => $this->getId(),
			'gateway_id'    => $this->getGatewayId(),
			'node_id'       => $this->getNodeId(),
			'uuid'          => $this->getUuid(),
			'category'      => $this->getCategory(),
			'category_name' => $this->getCategoryName(),
			'name'          => $this->getName(),
			'product_id'    => $this->getProductId(),
			'product_name'  => $this->getProductName(),
			'local_key'     => $this->getLocalKey(),
			'sub'           => $this->getSub(),
			'asset_id'      => $this->getAssetId(),
			'owner_id'      => $this->getOwnerId(),
			'ip'            => $this->getIp(),
			'lon'           => $this->getLon(),
			'lat'           => $this->getLat(),
			'model'         => $this->getModel(),
			'time_zone'     => $this->getTimeZone(),
			'active_time'   => $this->getActiveTime(),
			'create_time'   => $this->getCreateTime(),
			'update_time'   => $this->getUpdateTime(),
			'online'        => $this->isOnline(),
			'icon'          => $this->getIcon(),
		];
	}

}
