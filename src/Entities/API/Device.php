<?php declare(strict_types = 1);

/**
 * Device.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           26.04.22
 */

namespace FastyBird\Connector\Tuya\Entities\API;

use Nette\Utils;
use Orisai\ObjectMapper;

/**
 * OpenAPI device detail entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Device implements Entity
{

	public function __construct(
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private readonly string $id,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('gateway_id')]
		private readonly string|null $gatewayId,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('node_id')]
		private readonly string|null $nodeId,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly string|null $uuid,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly string|null $category,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('category_name')]
		private readonly string|null $categoryName,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly string|null $name,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('product_id')]
		private readonly string|null $productId,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('product_name')]
		private readonly string|null $productName,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('local_key')]
		private readonly string $localKey,
		#[ObjectMapper\Rules\BoolValue()]
		private readonly bool $sub,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('asset_id')]
		private readonly string|null $assetId,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('owner_id')]
		private readonly string|null $ownerId,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly string|null $ip,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly string|null $lon,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly string|null $lat,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly string|null $model,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('time_zone')]
		private readonly string|null $timeZone,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\IntValue(unsigned: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('active_time')]
		private readonly int|null $activeTime,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\IntValue(unsigned: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('create_time')]
		private readonly int|null $createTime,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\IntValue(unsigned: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('update_time')]
		private readonly int|null $updateTime,
		#[ObjectMapper\Rules\BoolValue()]
		private readonly bool $online,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly string|null $icon,
	)
	{
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getGatewayId(): string|null
	{
		return $this->gatewayId !== '' ? $this->gatewayId : null;
	}

	public function getNodeId(): string|null
	{
		return $this->nodeId !== '' ? $this->nodeId : null;
	}

	public function getUuid(): string|null
	{
		return $this->uuid !== '' ? $this->uuid : null;
	}

	public function getCategory(): string|null
	{
		return $this->category !== '' ? $this->category : null;
	}

	public function getCategoryName(): string|null
	{
		return $this->categoryName !== '' ? $this->categoryName : null;
	}

	public function getName(): string|null
	{
		return $this->name !== '' && $this->name !== null ? Utils\Strings::trim($this->name) : null;
	}

	public function getProductId(): string|null
	{
		return $this->productId !== '' ? $this->productId : null;
	}

	public function getProductName(): string|null
	{
		return $this->productName !== '' ? $this->productName : null;
	}

	public function getLocalKey(): string
	{
		return $this->localKey;
	}

	public function getSub(): bool
	{
		return $this->sub;
	}

	public function getAssetId(): string|null
	{
		return $this->assetId !== '' ? $this->assetId : null;
	}

	public function getOwnerId(): string|null
	{
		return $this->ownerId !== '' ? $this->ownerId : null;
	}

	public function getIp(): string|null
	{
		return $this->ip !== '' ? $this->ip : null;
	}

	public function getLon(): string|null
	{
		return $this->lon !== '' ? $this->lon : null;
	}

	public function getLat(): string|null
	{
		return $this->lat !== '' ? $this->lat : null;
	}

	public function getModel(): string|null
	{
		return $this->model !== '' ? $this->model : null;
	}

	public function getTimeZone(): string|null
	{
		return $this->timeZone !== '' ? $this->timeZone : null;
	}

	public function getActiveTime(): int|null
	{
		return $this->activeTime;
	}

	public function getCreateTime(): int|null
	{
		return $this->createTime;
	}

	public function getUpdateTime(): int|null
	{
		return $this->updateTime;
	}

	public function isOnline(): bool
	{
		return $this->online;
	}

	public function getIcon(): string|null
	{
		return $this->icon !== '' ? $this->icon : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->getId(),
			'gateway_id' => $this->getGatewayId(),
			'node_id' => $this->getNodeId(),
			'uuid' => $this->getUuid(),
			'category' => $this->getCategory(),
			'category_name' => $this->getCategoryName(),
			'name' => $this->getName(),
			'product_id' => $this->getProductId(),
			'product_name' => $this->getProductName(),
			'local_key' => $this->getLocalKey(),
			'sub' => $this->getSub(),
			'asset_id' => $this->getAssetId(),
			'owner_id' => $this->getOwnerId(),
			'ip' => $this->getIp(),
			'lon' => $this->getLon(),
			'lat' => $this->getLat(),
			'model' => $this->getModel(),
			'time_zone' => $this->getTimeZone(),
			'active_time' => $this->getActiveTime(),
			'create_time' => $this->getCreateTime(),
			'update_time' => $this->getUpdateTime(),
			'online' => $this->isOnline(),
			'icon' => $this->getIcon(),
		];
	}

}
