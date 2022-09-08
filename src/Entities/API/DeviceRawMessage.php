<?php declare(strict_types = 1);

/**
 * DeviceRawMessage.php
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

use FastyBird\TuyaConnector\Types;
use Nette;

/**
 * Local api device raw message entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceRawMessage implements Entity
{

	use Nette\SmartObject;

	/** @var string */
	private string $identifier;

	/** @var Types\LocalDeviceCommand */
	private Types\LocalDeviceCommand $command;

	/** @var int */
	private int $sequence;

	/** @var int|null */
	private ?int $returnCode;

	/** @var string|null */
	private ?string $data;

	/**
	 * @param string $identifier
	 * @param Types\LocalDeviceCommand $command
	 * @param int $sequence
	 * @param int|null $returnCode
	 * @param string|null $data
	 */
	public function __construct(
		string $identifier,
		Types\LocalDeviceCommand $command,
		int $sequence,
		?int $returnCode,
		?string $data
	) {
		$this->identifier = $identifier;
		$this->command = $command;
		$this->sequence = $sequence;
		$this->returnCode = $returnCode;
		$this->data = $data;
	}

	/**
	 * @return string
	 */
	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	/**
	 * @return Types\LocalDeviceCommand
	 */
	public function getCommand(): Types\LocalDeviceCommand
	{
		return $this->command;
	}

	/**
	 * @return int
	 */
	public function getSequence(): int
	{
		return $this->sequence;
	}

	/**
	 * @return int|null
	 */
	public function getReturnCode(): ?int
	{
		return $this->returnCode;
	}

	/**
	 * @return string|null
	 */
	public function getData(): ?string
	{
		return $this->data;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'identifier'  => $this->getIdentifier(),
			'command'     => $this->getCommand()->getValue(),
			'sequence'    => $this->getSequence(),
			'return_code' => $this->getReturnCode(),
			'data'        => $this->getData(),
		];
	}

}
