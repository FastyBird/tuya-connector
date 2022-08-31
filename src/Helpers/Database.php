<?php declare(strict_types = 1);

/**
 * Database.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Helpers
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Helpers;

use Doctrine\DBAL;
use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\TuyaConnector\Exceptions;
use Nette;
use Throwable;

/**
 * Useful database helpers
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Database
{

	use Nette\SmartObject;

	/** @var Persistence\ManagerRegistry */
	private Persistence\ManagerRegistry $managerRegistry;

	/**
	 * @param Persistence\ManagerRegistry $managerRegistry
	 */
	public function __construct(
		Persistence\ManagerRegistry $managerRegistry
	) {
		$this->managerRegistry = $managerRegistry;
	}

	/**
	 * @param callable(): T $callback
	 *
	 * @return T
	 *
	 * @throws DBAL\Exception
	 *
	 * @template T
	 */
	public function query(callable $callback)
	{
		try {
			$this->pingAndReconnect();

			return $callback();

		} catch (Throwable $ex) {
			throw new Exceptions\InvalidState('An error occurred: ' . $ex->getMessage(), $ex->getCode(), $ex);
		}
	}

	/**
	 * @param callable(): T $callback
	 *
	 * @return T
	 *
	 * @throws DBAL\Exception
	 *
	 * @template T
	 */
	public function transaction(callable $callback)
	{
		try {
			$this->pingAndReconnect();

			// Start transaction connection to the database
			$this->getConnection()->beginTransaction();

			$result = $callback();

			// Commit all changes into database
			$this->getConnection()->commit();

			return $result;

		} catch (Throwable $ex) {
			// Revert all changes when error occur
			if ($this->getConnection()->isTransactionActive()) {
				$this->getConnection()->rollBack();
			}

			throw new Exceptions\InvalidState('An error occurred: ' . $ex->getMessage(), $ex->getCode(), $ex);
		}
	}

	/**
	 * @return DBAL\Connection
	 */
	public function getConnection(): DBAL\Connection
	{
		$em = $this->getEntityManager();

		if ($em instanceof ORM\EntityManagerInterface) {
			return $em->getConnection();
		}

		throw new Exceptions\Runtime('Entity manager could not be loaded');
	}

	/**
	 * @return bool
	 */
	public function ping(): bool
	{
		$connection = $this->getConnection();

		try {
			$connection->executeQuery($connection->getDatabasePlatform()
				->getDummySelectSQL(), [], []);

		} catch (DBAL\Exception) {
			return false;
		}

		return true;
	}

	/**
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	public function reconnect(): void
	{
		$connection = $this->getConnection();

		$connection->close();
		$connection->connect();
	}

	/**
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function pingAndReconnect(): void
	{
		// Check if ping to DB is possible...
		if (!$this->ping()) {
			// ...if not, try to reconnect
			$this->reconnect();

			// ...and ping again
			if (!$this->ping()) {
				throw new Exceptions\Runtime('Connection to database could not be established');
			}

			$em = $this->getEntityManager();

			if ($em === null) {
				throw new Exceptions\Runtime('Entity manager could not be loaded');
			}

			$em->flush();
			$em->clear();

			// Just in case PHP would choose not to run garbage collection,
			// we run it manually at the end of each batch so that memory is
			// regularly released
			gc_collect_cycles();
		}
	}

	/**
	 * @return ORM\EntityManagerInterface|null
	 */
	private function getEntityManager(): ?ORM\EntityManagerInterface
	{
		$em = $this->managerRegistry->getManager();

		if ($em instanceof ORM\EntityManagerInterface) {
			if (!$em->isOpen()) {
				$this->managerRegistry->resetManager();

				$em = $this->managerRegistry->getManager();
			}

			if ($em instanceof ORM\EntityManagerInterface) {
				return $em;
			}
		}

		return null;
	}

}