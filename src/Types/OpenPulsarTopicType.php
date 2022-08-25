<?php declare(strict_types = 1);

/**
 * OpenPulsarTopicType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Types
 * @since          0.13.0
 *
 * @date           25.08.22
 */

namespace FastyBird\TuyaConnector\Types;

use Consistence;

/**
 * OpenPulsar topic types
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class OpenPulsarTopicType extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const TOPIC_PROD = 'event';
	public const TOPIC_TEST = 'event-test';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
