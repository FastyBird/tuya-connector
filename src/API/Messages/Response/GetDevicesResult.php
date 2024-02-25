<?php declare(strict_types = 1);

/**
 * GetDevicesResult.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           14.08.23
 */

namespace FastyBird\Connector\Tuya\API\Messages\Response;

use FastyBird\Connector\Tuya\API;
use Orisai\ObjectMapper;
use function array_map;

/**
 * OpenAPI get devices result message
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
readonly class GetDevicesResult implements API\Messages\Message
{

	/**
	 * @param array<Device> $list
	 */
	public function __construct(
		#[ObjectMapper\Rules\BoolValue()]
		#[ObjectMapper\Modifiers\FieldName('has_more')]
		private bool $hasMore,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		#[ObjectMapper\Modifiers\FieldName('last_row_key')]
		private string $lastRowKey,
		#[ObjectMapper\Rules\IntValue(unsigned: true)]
		private int $total,
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(Device::class),
		)]
		private array $list,
	)
	{
	}

	public function hasMore(): bool
	{
		return $this->hasMore;
	}

	public function getLastRowKey(): string
	{
		return $this->lastRowKey;
	}

	public function getTotal(): int
	{
		return $this->total;
	}

	/**
	 * @return array<Device>
	 */
	public function getList(): array
	{
		return $this->list;
	}

	public function toArray(): array
	{
		return [
			'has_more' => $this->hasMore(),
			'last_row_key' => $this->getLastRowKey(),
			'total' => $this->getTotal(),
			'list' => array_map(static fn (Device $status): array => $status->toArray(), $this->getList()),
		];
	}

}
