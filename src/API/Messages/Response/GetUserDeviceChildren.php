<?php declare(strict_types = 1);

/**
 * GetUserDeviceChildren.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           14.08.23
 */

namespace FastyBird\Connector\Tuya\API\Messages\Response;

use FastyBird\Connector\Tuya\API;
use Orisai\ObjectMapper;
use function array_map;

/**
 * OpenAPI get user device children message
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
readonly class GetUserDeviceChildren implements API\Messages\Message
{

	/**
	 * @param array<UserDeviceChild> $result
	 */
	public function __construct(
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(UserDeviceChild::class),
		)]
		private array $result,
	)
	{
	}

	/**
	 * @return array<UserDeviceChild>
	 */
	public function getResult(): array
	{
		return $this->result;
	}

	public function toArray(): array
	{
		return [
			'result' => array_map(static fn (UserDeviceChild $status): array => $status->toArray(), $this->getResult()),
		];
	}

}
