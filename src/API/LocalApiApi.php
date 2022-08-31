<?php declare(strict_types = 1);

/**
 * LocalApiApi.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 * @since          0.13.0
 *
 * @date           31.08.22
 */

namespace FastyBird\TuyaConnector\API;

use Nette;

/**
 * Local UDP device interface
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class LocalApiApi
{

	use Nette\SmartObject;

	/**
	 * @return void
	 */
	public function connect(): void
	{

	}

	/**
	 * @return void
	 */
	public function disconnect(): void
	{

	}

	/**
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return false;
	}

	public function readStates()
	{

	}

	public function writeStates(): bool
	{
		return false;
	}

	public function writeState(): bool
	{
		return false;
	}

}
