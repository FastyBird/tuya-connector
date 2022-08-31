<?php declare(strict_types = 1);

/**
 * MessageSourceType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Types
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Types;

use Consistence;

/**
 * Message client source types
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class MessageSourceType extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const SOURCE_LOCAL_API = 'local_api';
	public const SOURCE_CLOUD_OPENAPI = 'cloud_openapi';
	public const SOURCE_LOCAL_DISCOVERY = 'local_discovery';
	public const SOURCE_CLOUD_DISCOVERY = 'cloud_discovery';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
