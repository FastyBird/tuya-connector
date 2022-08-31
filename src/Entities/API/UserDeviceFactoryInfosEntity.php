<?php declare(strict_types = 1);

/**
 * UserDeviceFactoryInfosEntity.php
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
 * OpenAPI device factory info entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class UserDeviceFactoryInfosEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var string */
	private string $id;

	/** @var string|null */
	private ?string $uuid;

	/** @var string|null */
	private ?string $sn;

	/** @var string|null */
	private ?string $mac;

	/**
	 * @param string $id
	 * @param string|null $uuid
	 * @param string|null $sn
	 * @param string|null $mac
	 */
	public function __construct(
		string $id,
		?string $uuid,
		?string $sn,
		?string $mac
	) {
		$this->id = $id;
		$this->uuid = $uuid;
		$this->sn = $sn;
		$this->mac = $mac;
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
	public function getUuid(): ?string
	{
		return $this->uuid;
	}

	/**
	 * @return string|null
	 */
	public function getMac(): ?string
	{
		return $this->mac;
	}

	/**
	 * @return string|null
	 */
	public function getSn(): ?string
	{
		return $this->sn;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id'   => $this->getId(),
			'uuid' => $this->getUuid(),
			'sn'   => $this->getSn(),
			'mac'  => $this->getMac(),
		];
	}

}
