<?php declare(strict_types = 1);

/**
 * Device.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Properties
 * @since          0.13.0
 *
 * @date           04.09.22
 */

namespace FastyBird\TuyaConnector\Entities\Messages;

use FastyBird\TuyaConnector\Types;
use Nette;
use Ramsey\Uuid;

/**
 * Base device message entity
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Device implements Entity
{

	use Nette\SmartObject;

	/** @var Types\MessageSource */
	private Types\MessageSource $source;

	/** @var Uuid\UuidInterface */
	private Uuid\UuidInterface $connector;

	/** @var string */
	private string $identifier;

	/**
	 * @param Types\MessageSource $source
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 */
	public function __construct(
		Types\MessageSource $source,
		Uuid\UuidInterface $connector,
		string $identifier
	) {
		$this->source = $source;
		$this->connector = $connector;
		$this->identifier = $identifier;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): Types\MessageSource
	{
		return $this->source;
	}

	/**
	 * @return Uuid\UuidInterface
	 */
	public function getConnector(): Uuid\UuidInterface
	{
		return $this->connector;
	}

	/**
	 * @return string
	 */
	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'source'     => $this->getSource()->getValue(),
			'identifier' => $this->getIdentifier(),
		];
	}

}
