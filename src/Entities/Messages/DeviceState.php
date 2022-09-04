<?php declare(strict_types = 1);

/**
 * DeviceState.php
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
 * Device state message entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceState extends Device
{

	/** @var bool */
	private bool $online;

	/**
	 * @param Types\MessageSource $source
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 * @param bool $online
	 */
	public function __construct(
		Types\MessageSource $source,
		Uuid\UuidInterface $connector,
		string $identifier,
		bool $online
	) {
		parent::__construct($source, $connector, $identifier);

		$this->online = $online;
	}

	/**
	 * @return bool
	 */
	public function isOnline(): bool
	{
		return $this->online;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'online' => $this->isOnline(),
		]);
	}

}
