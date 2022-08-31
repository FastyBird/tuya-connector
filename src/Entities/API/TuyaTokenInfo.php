<?php declare(strict_types = 1);

/**
 * TuyaTokenInfo.php
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
 * OpenAPI token entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class TuyaTokenInfo implements Entity
{

	use Nette\SmartObject;

	/** @var string */
	private string $uid;

	/** @var string */
	private string $accessToken;

	/** @var string */
	private string $refreshToken;

	/** @var int */
	private int $expireTime;

	/**
	 * @param string $uid
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param int $expireTime
	 */
	public function __construct(
		string $uid,
		string $accessToken,
		string $refreshToken,
		int $expireTime
	) {
		$this->uid = $uid;
		$this->accessToken = $accessToken;
		$this->refreshToken = $refreshToken;
		$this->expireTime = $expireTime;
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
	public function getAccessToken(): string
	{
		return $this->accessToken;
	}

	/**
	 * @return string
	 */
	public function getRefreshToken(): string
	{
		return $this->refreshToken;
	}

	/**
	 * @return int
	 */
	public function getExpireTime(): int
	{
		return $this->expireTime;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'uid'           => $this->getUid(),
			'access_token'  => $this->getAccessToken(),
			'refresh_token' => $this->getRefreshToken(),
			'expire_time'   => $this->getExpireTime(),
		];
	}

}
