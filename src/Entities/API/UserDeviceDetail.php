<?php declare(strict_types = 1);

/**
 * UserDeviceDetail.php
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
 * OpenAPI device detail entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class UserDeviceDetail implements Entity
{

	use Nette\SmartObject;

	/** @var string */
	private string $id;

	/** @var string|null */
	private ?string $name;

	/** @var string */
	private string $uid;

	/** @var string */
	private string $localKey;

	/** @var string|null */
	private ?string $category;

	/** @var string|null */
	private ?string $productId;

	/** @var string|null */
	private ?string $productName;

	/** @var bool */
	private bool $sub;

	/** @var string */
	private string $uuid;

	/** @var string|null */
	private ?string $ownerId;

	/** @var bool */
	private bool $online;

	/** @var UserDeviceStatus[] */
	private array $status;

	/** @var int|null */
	private ?int $activeTime;

	/** @var int|null */
	private ?int $createTime;

	/** @var int|null */
	private ?int $updateTime;

	/** @var int|null */
	private ?int $bizType;

	/** @var string|null */
	private ?string $icon;

	/** @var string|null */
	private ?string $ip;

	/** @var string|null */
	private ?string $timeZone;

	/**
	 * @param string $id
	 * @param string|null $name
	 * @param string $uid
	 * @param string $localKey
	 * @param string|null $category
	 * @param string|null $productId
	 * @param string|null $productName
	 * @param bool $sub
	 * @param string $uuid
	 * @param string|null $ownerId
	 * @param bool $online
	 * @param UserDeviceStatus[] $status
	 * @param int|null $activeTime
	 * @param int|null $createTime
	 * @param int|null $updateTime
	 * @param int|null $bizType
	 * @param string|null $icon
	 * @param string|null $ip
	 * @param string|null $timeZone
	 */
	public function __construct(
		string $id,
		?string $name,
		string $uid,
		string $localKey,
		?string $category,
		?string $productId,
		?string $productName,
		bool $sub,
		string $uuid,
		?string $ownerId,
		bool $online,
		array $status,
		?int $activeTime,
		?int $createTime,
		?int $updateTime,
		?int $bizType,
		?string $icon,
		?string $ip,
		?string $timeZone
	) {
		$this->id = $id;
		$this->name = $name;
		$this->uid = $uid;
		$this->localKey = $localKey;
		$this->category = $category;
		$this->productId = $productId;
		$this->productName = $productName;
		$this->sub = $sub;
		$this->uuid = $uuid;
		$this->ownerId = $ownerId;
		$this->online = $online;
		$this->status = $status;
		$this->activeTime = $activeTime;
		$this->createTime = $createTime;
		$this->updateTime = $updateTime;
		$this->bizType = $bizType;
		$this->icon = $icon;
		$this->ip = $ip;
		$this->timeZone = $timeZone;
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
	public function getName(): ?string
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
	public function getLocalKey(): string
	{
		return $this->localKey;
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
	 * @return bool
	 */
	public function getSub(): bool
	{
		return $this->sub;
	}

	/**
	 * @return string
	 */
	public function getUuid(): string
	{
		return $this->uuid;
	}

	/**
	 * @return string|null
	 */
	public function getOwnerId(): ?string
	{
		return $this->ownerId;
	}

	/**
	 * @return bool
	 */
	public function isOnline(): bool
	{
		return $this->online;
	}

	/**
	 * @return UserDeviceStatus[]
	 */
	public function getStatus(): array
	{
		return $this->status;
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
	 * @return int|null
	 */
	public function getBizType(): ?int
	{
		return $this->bizType;
	}

	/**
	 * @return string|null
	 */
	public function getIcon(): ?string
	{
		return $this->icon;
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
	public function getTimeZone(): ?string
	{
		return $this->timeZone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id'           => $this->getId(),
			'name'         => $this->getName(),
			'uid'          => $this->getUid(),
			'local_key'    => $this->getLocalKey(),
			'category'     => $this->getCategory(),
			'product_id'   => $this->getProductId(),
			'product_name' => $this->getProductName(),
			'sub'          => $this->getSub(),
			'uuid'         => $this->getUuid(),
			'owner_id'     => $this->getOwnerId(),
			'online'       => $this->isOnline(),
			'status'       => array_map(function (UserDeviceStatus $item): array {
				return $item->toArray();
			}, $this->getStatus()),
			'active_time'  => $this->getActiveTime(),
			'create_time'  => $this->getCreateTime(),
			'update_time'  => $this->getUpdateTime(),
			'biz_type'     => $this->getBizType(),
			'icon'         => $this->getIcon(),
			'ip'           => $this->getIp(),
			'time_zone'    => $this->getTimeZone(),
		];
	}

}
