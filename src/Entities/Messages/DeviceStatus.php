<?php declare(strict_types = 1);

/**
 * DeviceDataPointStatus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           04.09.22
 */

namespace FastyBird\Connector\Tuya\Entities\Messages;

use Ramsey\Uuid;
use function array_map;
use function array_merge;

/**
 * Device status message entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceStatus extends Device
{

	/**
	 * @param array<DataPointStatus> $dataPoints
	 */
	public function __construct(
		Uuid\UuidInterface $connector,
		string $identifier,
		private readonly array $dataPoints,
	)
	{
		parent::__construct($connector, $identifier);
	}

	/**
	 * @return array<DataPointStatus>
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
		return array_merge(parent::toArray(), [
			'data_points' => array_map(
				static fn (DataPointStatus $channel): array => $channel->toArray(),
				$this->getDataPoints(),
			),
		]);
	}

}
