<?php declare(strict_types = 1);

/**
 * DiscoveredLocalDevice.php
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

namespace FastyBird\TuyaConnector\Entities\API;

use Nette;

/**
 * Discovered local device entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DiscoveredLocalDevice implements Entity
{

	use Nette\SmartObject;

	/** @var string */
	private string $id;

	/** @var string */
	private string $ipAddress;

	/** @var string */
	private string $productKey;

	/** @var bool */
	private bool $encrypted;

	/** @var string */
	private string $version;

	/**
	 * @param string $id
	 * @param string $ipAddress
	 * @param string $productKey
	 * @param bool $encrypted
	 * @param string $version
	 */
	public function __construct(
		string $id,
		string $ipAddress,
		string $productKey,
		bool $encrypted,
		string $version,
	) {
		$this->id = $id;
		$this->ipAddress = $ipAddress;
		$this->productKey = $productKey;
		$this->encrypted = $encrypted;
		$this->version = $version;
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
	public function getIpAddress(): string
	{
		return $this->ipAddress;
	}

	/**
	 * @return string
	 */
	public function getProductKey(): string
	{
		return $this->productKey;
	}

	/**
	 * @return bool
	 */
	public function isEncrypted(): bool
	{
		return $this->encrypted;
	}

	/**
	 * @return string
	 */
	public function getVersion(): string
	{
		return $this->version;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id'          => $this->id,
			'ip_address'  => $this->ipAddress,
			'product_key' => $this->productKey,
			'encrypted'   => $this->encrypted,
			'version'     => $this->version,
		];
	}

}
