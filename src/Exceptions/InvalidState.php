<?php declare(strict_types = 1);

/**
 * InvalidState.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Exceptions
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Exceptions;

use RuntimeException;

class InvalidState extends RuntimeException implements Exception
{

}