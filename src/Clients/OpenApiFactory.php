<?php declare(strict_types = 1);

/**
 * OpenApiFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 * @since          0.13.0
 *
 * @date           25.08.22
 */

namespace FastyBird\TuyaConnector\Clients;

use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\TuyaConnector\Types;

/**
 * Cloud OpenAPI devices client factory
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface OpenApiFactory extends ClientFactory
{

	public const MODE = Types\ClientMode::MODE_CLOUD;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return OpenApi
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): OpenApi;

}
