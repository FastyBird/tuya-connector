<?php declare(strict_types = 1);

/**
 * IClient.php
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

use FastyBird\Metadata\Entities as MetadataEntities;

/**
 * Tuya device client interface
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IClient
{

	/**
	 * @param MetadataEntities\Actions\IActionDeviceControlEntity $action
	 *
	 * @return void
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void;

	/**
	 * @param MetadataEntities\Actions\IActionChannelControlEntity $action
	 *
	 * @return void
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void;

	/**
	 * @return bool
	 */
	public function isConnected(): bool;

	/**
	 * Discover new devices
	 *
	 * @return void
	 */
	public function discover(): void;

	/**
	 * Create servers/clients
	 *
	 * @return void
	 */
	public function connect(): void;

	/**
	 * Destroy servers/clients
	 *
	 * @return void
	 */
	public function disconnect(): void;

}
