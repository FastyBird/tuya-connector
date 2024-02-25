<?php declare(strict_types = 1);

/**
 * Constants.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     common
 * @since          1.0.0
 *
 * @date           28.08.22
 */

namespace FastyBird\Connector\Tuya;

/**
 * Connector constants
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Constants
{

	public const RESOURCES_FOLDER = __DIR__ . '/../resources';

	public const EVENT_MESSAGE = 'message';

	public const EVENT_ERROR = 'error';

	public const EVENT_DISCONNECTED = 'disconnected';

	public const EVENT_CONNECTED = 'connected';

	public const EVENT_LOST = 'lost';

}
