<?php declare(strict_types = 1);

/**
 * Client.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Clients;

use Nette;
use FastyBird\Metadata\Entities as MetadataEntities;

/**
 * Base client service
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Client
{

	use Nette\SmartObject;

	/**
	 * @param MetadataEntities\Actions\IActionDeviceControlEntity $action
	 *
	 * @return void
	 */
	abstract public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void;

	/**
	 * @param MetadataEntities\Actions\IActionChannelControlEntity $action
	 *
	 * @return void
	 */
	abstract public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void;

	/**
	 * @return bool
	 */
	abstract public function isConnected(): bool;

	/**
	 * Discover new devices
	 *
	 * @return void
	 */
	abstract public function discover(): void;

	/**
	 * Create servers/clients
	 *
	 * @return void
	 */
	abstract public function connect(): void;

	/**
	 * Destroy servers/clients
	 *
	 * @return void
	 */
	abstract public function disconnect(): void;

}
