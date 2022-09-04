<?php declare(strict_types = 1);

/**
 * DeviceStatus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Properties
 * @since          0.13.0
 *
 * @date           04.09.22
 */

namespace FastyBird\TuyaConnector\Entities\Messages;

use FastyBird\TuyaConnector\Types;
use Ramsey\Uuid;

/**
 * Device status message entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceStatus extends Device
{

	/** @var DataPointStatus[] */
	private array $dataPoints;

	/**
	 * @param Types\MessageSource $source
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 * @param DataPointStatus[] $dataPoints
	 */
	public function __construct(
		Types\MessageSource $source,
		Uuid\UuidInterface $connector,
		string $identifier,
		array $dataPoints
	) {
		parent::__construct($source, $connector, $identifier);

		$this->dataPoints = $dataPoints;
	}

	/**
	 * @return DataPointStatus[]
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
			'data_points' => array_map(function (DataPointStatus $channel): array {
				return $channel->toArray();
			}, $this->getDataPoints()),
		]);
	}

}
