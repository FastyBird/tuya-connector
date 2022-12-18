<?php declare(strict_types = 1);

/**
 * DeviceFactoryInfos.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 * @since          0.13.0
 *
 * @date           29.04.22
 */

namespace FastyBird\Connector\Tuya\Entities\API;

use Nette;

/**
 * OpenAPI device factory info entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceFactoryInfos implements Entity
{

	use Nette\SmartObject;

	public function __construct(
		private readonly string $id,
		private readonly string|null $uuid,
		private readonly string|null $sn,
		private readonly string|null $mac,
	)
	{
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getUuid(): string|null
	{
		return $this->uuid !== '' ? $this->uuid : null;
	}

	public function getMac(): string|null
	{
		return $this->mac !== '' ? $this->mac : null;
	}

	public function getSn(): string|null
	{
		return $this->sn !== '' ? $this->sn : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->getId(),
			'uuid' => $this->getUuid(),
			'sn' => $this->getSn(),
			'mac' => $this->getMac(),
		];
	}

}
