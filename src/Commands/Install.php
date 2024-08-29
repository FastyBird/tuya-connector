<?php declare(strict_types = 1);

/**
 * Install.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Commands
 * @since          1.0.0
 *
 * @date           13.12.23
 */

namespace FastyBird\Connector\Tuya\Commands;

use DateTimeImmutable;
use Doctrine\DBAL;
use FastyBird\Connector\Tuya;
use FastyBird\Connector\Tuya\Entities;
use FastyBird\Connector\Tuya\Exceptions;
use FastyBird\Connector\Tuya\Queries;
use FastyBird\Connector\Tuya\Types;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Application\Exceptions as ApplicationExceptions;
use FastyBird\Library\Application\Helpers as ApplicationHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Commands as DevicesCommands;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Types as DevicesTypes;
use Nette\Localization;
use Nette\Utils;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;
use TypeError;
use ValueError;
use function array_key_exists;
use function array_search;
use function array_values;
use function assert;
use function count;
use function sprintf;
use function strval;
use function usort;

/**
 * Connector install command
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Install extends Console\Command\Command
{

	public const NAME = 'fb:tuya-connector:install';

	private Input\InputInterface|null $input = null;

	private Output\OutputInterface|null $output = null;

	public function __construct(
		private readonly Tuya\Logger $logger,
		private readonly DevicesModels\Entities\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Entities\Connectors\ConnectorsManager $connectorsManager,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesRepository $propertiesRepository,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesManager $propertiesManager,
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Entities\Devices\DevicesManager $devicesManager,
		private readonly ApplicationHelpers\Database $databaseHelper,
		private readonly DateTimeFactory\Clock $clock,
		private readonly Localization\Translator $translator,
		string|null $name = null,
	)
	{
		parent::__construct($name);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 */
	protected function configure(): void
	{
		$this
			->setName(self::NAME)
			->setDescription('Tuya connector installer');
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws Console\Exception\ExceptionInterface
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$this->input = $input;
		$this->output = $output;

		$io = new Style\SymfonyStyle($this->input, $this->output);

		$io->title((string) $this->translator->translate('//tuya-connector.cmd.install.title'));

		$io->note((string) $this->translator->translate('//tuya-connector.cmd.install.subtitle'));

		$this->askInstallAction($io);

		return Console\Command\Command::SUCCESS;
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function createConnector(Style\SymfonyStyle $io): void
	{
		$mode = $this->askConnectorMode($io);

		$question = new Console\Question\Question(
			(string) $this->translator->translate(
				'//tuya-connector.cmd.install.questions.provide.connector.identifier',
			),
		);

		$question->setValidator(function ($answer) {
			if ($answer !== null) {
				$findConnectorQuery = new Queries\Entities\FindConnectors();
				$findConnectorQuery->byIdentifier($answer);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\Connectors\Connector::class,
				);

				if ($connector !== null) {
					throw new Exceptions\Runtime(
						(string) $this->translator->translate(
							'//tuya-connector.cmd.install.messages.identifier.connector.used',
						),
					);
				}
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'tuya-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				$findConnectorQuery = new Queries\Entities\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\Connectors\Connector::class,
				);

				if ($connector === null) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error(
				(string) $this->translator->translate(
					'//tuya-connector.cmd.install.messages.identifier.connector.missing',
				),
			);

			return;
		}

		$name = $this->askConnectorName($io);

		$accessId = $this->askConnectorAccessId($io);

		$accessSecret = $this->askConnectorAccessSecret($io);

		$dataCentre = $this->askConnectorOpenApiEndpoint($io);

		$uid = null;

		if ($mode === Types\ClientMode::CLOUD) {
			$uid = $this->askConnectorUid($io);
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$connector = $this->connectorsManager->create(Utils\ArrayHash::from([
				'entity' => Entities\Connectors\Connector::class,
				'identifier' => $identifier,
				'name' => $name === '' ? null : $name,
			]));
			assert($connector instanceof Entities\Connectors\Connector);

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::CLIENT_MODE->value,
				'dataType' => MetadataTypes\DataType::ENUM,
				'value' => $mode->value,
				'format' => [Types\ClientMode::LOCAL->value, Types\ClientMode::CLOUD->value],
				'connector' => $connector,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::ACCESS_ID->value,
				'dataType' => MetadataTypes\DataType::STRING,
				'value' => $accessId,
				'connector' => $connector,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::ACCESS_SECRET->value,
				'dataType' => MetadataTypes\DataType::STRING,
				'value' => $accessSecret,
				'connector' => $connector,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::OPENAPI_ENDPOINT->value,
				'dataType' => MetadataTypes\DataType::ENUM,
				'value' => $dataCentre->value,
				'format' => [
					Types\OpenApiEndpoint::EUROPE->value,
					Types\OpenApiEndpoint::EUROPE_MS->value,
					Types\OpenApiEndpoint::AMERICA->value,
					Types\OpenApiEndpoint::AMERICA_AZURE->value,
					Types\OpenApiEndpoint::CHINA->value,
					Types\OpenApiEndpoint::INDIA->value,
				],
				'connector' => $connector,
			]));

			if ($mode === Types\ClientMode::CLOUD) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::UID->value,
					'dataType' => MetadataTypes\DataType::STRING,
					'value' => $uid,
					'connector' => $connector,
				]));
			}

			// Commit all changes into database
			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//tuya-connector.cmd.install.messages.create.connector.success',
					['name' => $connector->getName() ?? $connector->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::TUYA->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate('//tuya-connector.cmd.install.messages.create.connector.error'),
			);

			return;
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function editConnector(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->warning((string) $this->translator->translate('//tuya-connector.cmd.base.messages.noConnectors'));

			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//tuya-connector.cmd.install.questions.create.connector'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createConnector($io);
			}

			return;
		}

		$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::CLIENT_MODE);

		$modeProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

		if ($modeProperty === null) {
			$changeMode = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//tuya-connector.cmd.install.questions.change.mode'),
				false,
			);

			$changeMode = (bool) $io->askQuestion($question);
		}

		$mode = null;

		if ($changeMode) {
			$mode = $this->askConnectorMode($io);
		}

		$name = $this->askConnectorName($io, $connector);

		$enabled = $connector->isEnabled();

		if ($connector->isEnabled()) {
			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//tuya-connector.cmd.install.questions.disable.connector'),
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = false;
			}
		} else {
			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//tuya-connector.cmd.install.questions.enable.connector'),
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = true;
			}
		}

		$accessId = $accessSecret = null;

		$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::ACCESS_ID);

		$accessIdProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

		if ($accessIdProperty === null) {
			$changeAccessId = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//tuya-connector.cmd.install.questions.change.accessId'),
				false,
			);

			$changeAccessId = (bool) $io->askQuestion($question);
		}

		if ($changeAccessId) {
			$accessId = $this->askConnectorAccessId($io, $connector);
		}

		$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::ACCESS_SECRET);

		$accessSecretProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

		if ($accessSecretProperty === null) {
			$changeAccessSecret = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//tuya-connector.cmd.install.questions.change.accessSecret'),
				false,
			);

			$changeAccessSecret = (bool) $io->askQuestion($question);
		}

		if ($changeAccessSecret) {
			$accessSecret = $this->askConnectorAccessSecret($io, $connector);
		}

		$uid = null;
		$uidProperty = null;

		if (
			(
				$modeProperty !== null
				&& $modeProperty->getValue() === Types\ClientMode::CLOUD->value
			) || (
				$mode !== null
				&& $mode === Types\ClientMode::CLOUD
			)
		) {
			$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($connector);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::UID);

			$uidProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($uidProperty === null) {
				$changeUid = true;

			} else {
				$question = new Console\Question\ConfirmationQuestion(
					(string) $this->translator->translate('//tuya-connector.cmd.install.questions.change.user'),
					false,
				);

				$changeUid = (bool) $io->askQuestion($question);
			}

			if ($changeUid) {
				$uid = $this->askConnectorUid($io, $connector);
			}
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$connector = $this->connectorsManager->update($connector, Utils\ArrayHash::from([
				'name' => $name === '' ? null : $name,
				'enabled' => $enabled,
			]));
			assert($connector instanceof Entities\Connectors\Connector);

			if ($modeProperty === null) {
				if ($mode === null) {
					$mode = $this->askConnectorMode($io);
				}

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::CLIENT_MODE->value,
					'dataType' => MetadataTypes\DataType::ENUM,
					'value' => $mode->value,
					'format' => [Types\ClientMode::LOCAL->value, Types\ClientMode::CLOUD->value],
					'connector' => $connector,
				]));
			} elseif ($mode !== null) {
				$this->propertiesManager->update($modeProperty, Utils\ArrayHash::from([
					'value' => $mode->value,
				]));
			}

			if ($accessIdProperty === null) {
				if ($accessId === null) {
					$accessId = $this->askConnectorAccessId($io, $connector);
				}

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::ACCESS_ID->value,
					'dataType' => MetadataTypes\DataType::STRING,
					'value' => $accessId,
					'connector' => $connector,
				]));
			} elseif ($accessId !== null) {
				$this->propertiesManager->update($accessIdProperty, Utils\ArrayHash::from([
					'value' => $accessId,
				]));
			}

			if ($accessSecretProperty === null) {
				if ($accessSecret === null) {
					$accessSecret = $this->askConnectorAccessSecret($io, $connector);
				}

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::ACCESS_SECRET->value,
					'dataType' => MetadataTypes\DataType::STRING,
					'value' => $accessSecret,
					'connector' => $connector,
				]));
			} elseif ($accessSecret !== null) {
				$this->propertiesManager->update($accessSecretProperty, Utils\ArrayHash::from([
					'value' => $accessSecret,
				]));
			}

			if (
				(
					$modeProperty !== null
					&& $modeProperty->getValue() === Types\ClientMode::CLOUD->value
				) || (
					$mode !== null
					&& $mode === Types\ClientMode::CLOUD
				)
			) {
				if ($uidProperty === null) {
					if ($uid === null) {
						$uid = $this->askConnectorUid($io, $connector);
					}

					$this->propertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::UID->value,
						'dataType' => MetadataTypes\DataType::STRING,
						'value' => $uid,
						'connector' => $connector,
					]));
				} elseif ($uid !== null) {
					$this->propertiesManager->update($uidProperty, Utils\ArrayHash::from([
						'value' => $uid,
					]));
				}
			} else {
				if ($uidProperty !== null) {
					$this->propertiesManager->delete($uidProperty);
				}
			}

			// Commit all changes into database
			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//tuya-connector.cmd.install.messages.update.connector.success',
					['name' => $connector->getName() ?? $connector->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::TUYA->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate('//tuya-connector.cmd.install.messages.update.connector.error'),
			);

			return;
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 */
	private function deleteConnector(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->info((string) $this->translator->translate('//tuya-connector.cmd.base.messages.noConnectors'));

			return;
		}

		$io->warning(
			(string) $this->translator->translate(
				'//tuya-connector.cmd.install.messages.remove.connector.confirm',
				['name' => $connector->getName() ?? $connector->getIdentifier()],
			),
		);

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//tuya-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$this->connectorsManager->delete($connector);

			// Commit all changes into database
			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//tuya-connector.cmd.install.messages.remove.connector.success',
					['name' => $connector->getName() ?? $connector->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::TUYA->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate('//tuya-connector.cmd.install.messages.remove.connector.error'),
			);
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws Console\Exception\ExceptionInterface
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function manageConnector(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->info((string) $this->translator->translate('//tuya-connector.cmd.base.messages.noConnectors'));

			return;
		}

		$this->askManageConnectorAction($io, $connector);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function listConnectors(Style\SymfonyStyle $io): void
	{
		$findConnectorsQuery = new Queries\Entities\FindConnectors();

		$connectors = $this->connectorsRepository->findAllBy(
			$findConnectorsQuery,
			Entities\Connectors\Connector::class,
		);
		usort(
			$connectors,
			static fn (Entities\Connectors\Connector $a, Entities\Connectors\Connector $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.name'),
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.mode'),
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.devicesCnt'),
		]);

		foreach ($connectors as $index => $connector) {
			$findDevicesQuery = new Queries\Entities\FindDevices();
			$findDevicesQuery->forConnector($connector);

			$devices = $this->devicesRepository->findAllBy($findDevicesQuery, Entities\Devices\Device::class);

			$table->addRow([
				$index + 1,
				$connector->getName() ?? $connector->getIdentifier(),
				(string) $this->translator->translate(
					'//tuya-connector.cmd.base.mode.' . $connector->getClientMode()->value,
				),
				count($devices),
			]);
		}

		$table->render();

		$io->newLine();
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 */
	private function editDevice(Style\SymfonyStyle $io, Entities\Connectors\Connector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->info((string) $this->translator->translate('//tuya-connector.cmd.install.messages.noDevices'));

			return;
		}

		$name = $this->askDeviceName($io, $device);

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$device = $this->devicesManager->update($device, Utils\ArrayHash::from([
				'name' => $name,
			]));
			assert($device instanceof Entities\Devices\Device);

			// Commit all changes into database
			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//tuya-connector.cmd.install.messages.update.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::TUYA->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate('//tuya-connector.cmd.install.messages.update.device.error'),
			);

			return;
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 */
	private function deleteDevice(Style\SymfonyStyle $io, Entities\Connectors\Connector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->info((string) $this->translator->translate('//tuya-connector.cmd.install.messages.noDevices'));

			return;
		}

		$io->warning(
			(string) $this->translator->translate(
				'//tuya-connector.cmd.install.messages.remove.device.confirm',
				['name' => $device->getName() ?? $device->getIdentifier()],
			),
		);

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//tuya-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$this->devicesManager->delete($device);

			// Commit all changes into database
			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//tuya-connector.cmd.install.messages.remove.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::TUYA->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate('//tuya-connector.cmd.install.messages.remove.device.error'),
			);
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function listDevices(Style\SymfonyStyle $io, Entities\Connectors\Connector $connector): void
	{
		$findDevicesQuery = new Queries\Entities\FindDevices();
		$findDevicesQuery->forConnector($connector);

		$devices = $this->devicesRepository->findAllBy($findDevicesQuery, Entities\Devices\Device::class);
		usort(
			$devices,
			static fn (Entities\Devices\Device $a, Entities\Devices\Device $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.name'),
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.model'),
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.ipAddress'),
		]);

		foreach ($devices as $index => $device) {
			$table->addRow([
				$index + 1,
				$device->getName() ?? $device->getIdentifier(),
				$device->getModel(),
				$device->getIpAddress() ?? 'N/A',
			]);
		}

		$table->render();

		$io->newLine();
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws Console\Exception\ExceptionInterface
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function discoverDevices(Style\SymfonyStyle $io, Entities\Connectors\Connector $connector): void
	{
		if ($this->output === null) {
			throw new Exceptions\InvalidState('Something went wrong, console output is not configured');
		}

		$executedTime = $this->clock->getNow();
		assert($executedTime instanceof DateTimeImmutable);
		$executedTime = $executedTime->modify('-5 second');

		$symfonyApp = $this->getApplication();

		if ($symfonyApp === null) {
			throw new Exceptions\InvalidState('Something went wrong, console app is not configured');
		}

		$serviceCmd = $symfonyApp->find(DevicesCommands\Connector::NAME);

		$io->info((string) $this->translator->translate('//tuya-connector.cmd.install.messages.discover.starting'));

		$result = $serviceCmd->run(new Input\ArrayInput([
			'--connector' => $connector->getId()->toString(),
			'--mode' => DevicesTypes\ConnectorMode::DISCOVER->value,
			'--no-interaction' => true,
			'--quiet' => true,
		]), $this->output);

		$this->databaseHelper->clear();

		$io->newLine(2);

		$io->info((string) $this->translator->translate('//tuya-connector.cmd.install.messages.discover.stopping'));

		if ($result !== Console\Command\Command::SUCCESS) {
			$io->error((string) $this->translator->translate('//tuya-connector.cmd.install.messages.discover.error'));

			return;
		}

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.id'),
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.name'),
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.model'),
			(string) $this->translator->translate('//tuya-connector.cmd.install.data.ipAddress'),
		]);

		$foundDevices = 0;

		$findDevicesQuery = new Queries\Entities\FindDevices();
		$findDevicesQuery->forConnector($connector);

		$devices = $this->devicesRepository->findAllBy($findDevicesQuery, Entities\Devices\Device::class);

		foreach ($devices as $device) {
			$createdAt = $device->getCreatedAt();

			if (
				$createdAt !== null
				&& $createdAt->getTimestamp() > $executedTime->getTimestamp()
			) {
				$foundDevices++;

				$table->addRow([
					$foundDevices,
					$device->getId()->toString(),
					$device->getName() ?? $device->getIdentifier(),
					$device->getModel(),
					$device->getIpAddress() ?? 'N/A',
				]);
			}
		}

		if ($foundDevices > 0) {
			$io->info(sprintf(
				(string) $this->translator->translate('//tuya-connector.cmd.install.messages.foundDevices'),
				$foundDevices,
			));

			$table->render();

			$io->newLine();

		} else {
			$io->info((string) $this->translator->translate('//tuya-connector.cmd.install.messages.noDevicesFound'));
		}

		$io->success((string) $this->translator->translate('//tuya-connector.cmd.install.messages.discover.success'));
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws Console\Exception\ExceptionInterface
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askInstallAction(Style\SymfonyStyle $io): void
	{
		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//tuya-connector.cmd.base.questions.whatToDo'),
			[
				0 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.create.connector'),
				1 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.update.connector'),
				2 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.remove.connector'),
				3 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.manage.connector'),
				4 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.list.connectors'),
				5 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.nothing'),
			],
			5,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === (string) $this->translator->translate(
				'//tuya-connector.cmd.install.actions.create.connector',
			)
			|| $whatToDo === '0'
		) {
			$this->createConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//tuya-connector.cmd.install.actions.update.connector',
			)
			|| $whatToDo === '1'
		) {
			$this->editConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//tuya-connector.cmd.install.actions.remove.connector',
			)
			|| $whatToDo === '2'
		) {
			$this->deleteConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//tuya-connector.cmd.install.actions.manage.connector',
			)
			|| $whatToDo === '3'
		) {
			$this->manageConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//tuya-connector.cmd.install.actions.list.connectors',
			)
			|| $whatToDo === '4'
		) {
			$this->listConnectors($io);

			$this->askInstallAction($io);
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws Console\Exception\ExceptionInterface
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askManageConnectorAction(
		Style\SymfonyStyle $io,
		Entities\Connectors\Connector $connector,
	): void
	{
		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//tuya-connector.cmd.base.questions.whatToDo'),
			[
				0 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.update.device'),
				1 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.remove.device'),
				2 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.list.devices'),
				3 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.discover.devices'),
				4 => (string) $this->translator->translate('//tuya-connector.cmd.install.actions.nothing'),
			],
			4,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === (string) $this->translator->translate(
				'//tuya-connector.cmd.install.actions.update.device',
			)
			|| $whatToDo === '0'
		) {
			$this->editDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//tuya-connector.cmd.install.actions.remove.device',
			)
			|| $whatToDo === '1'
		) {
			$this->deleteDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//tuya-connector.cmd.install.actions.list.devices',
			)
			|| $whatToDo === '2'
		) {
			$this->listDevices($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//tuya-connector.cmd.install.actions.discover.devices',
			)
			|| $whatToDo === '3'
		) {
			$this->discoverDevices($io, $connector);

			$this->askManageConnectorAction($io, $connector);
		}
	}

	private function askConnectorMode(Style\SymfonyStyle $io): Types\ClientMode
	{
		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//tuya-connector.cmd.install.questions.select.connector.mode'),
			[
				0 => (string) $this->translator->translate('//tuya-connector.cmd.install.answers.mode.local'),
				1 => (string) $this->translator->translate('//tuya-connector.cmd.install.answers.mode.cloud'),
			],
			1,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer): Types\ClientMode {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (
				$answer === (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.mode.local',
				)
				|| $answer === '0'
			) {
				return Types\ClientMode::LOCAL;
			}

			if (
				$answer === (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.mode.cloud',
				)
				|| $answer === '1'
			) {
				return Types\ClientMode::CLOUD;
			}

			throw new Exceptions\Runtime(
				sprintf(
					(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\ClientMode);

		return $answer;
	}

	private function askConnectorName(
		Style\SymfonyStyle $io,
		Entities\Connectors\Connector|null $connector = null,
	): string|null
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate('//tuya-connector.cmd.install.questions.provide.connector.name'),
			$connector?->getName(),
		);

		$name = $io->askQuestion($question);

		return strval($name) === '' ? null : strval($name);
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askConnectorAccessId(
		Style\SymfonyStyle $io,
		Entities\Connectors\Connector|null $connector = null,
	): string
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate('//tuya-connector.cmd.install.questions.provide.connector.accessId'),
			$connector?->getAccessId(),
		);
		$question->setValidator(function (string|null $answer): string {
			if ($answer === '' || $answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			return $answer;
		});

		return strval($io->askQuestion($question));
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askConnectorAccessSecret(
		Style\SymfonyStyle $io,
		Entities\Connectors\Connector|null $connector = null,
	): string
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate(
				'//tuya-connector.cmd.install.questions.provide.connector.accessSecret',
			),
			$connector?->getAccessSecret(),
		);
		$question->setValidator(function (string|null $answer): string {
			if ($answer === '' || $answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			return $answer;
		});

		return strval($io->askQuestion($question));
	}

	private function askConnectorOpenApiEndpoint(Style\SymfonyStyle $io): Types\OpenApiEndpoint
	{
		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//tuya-connector.cmd.install.questions.select.connector.dataCentre'),
			[
				0 => (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.centralEurope',
				),
				1 => (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.westernEurope',
				),
				2 => (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.westernAmerica',
				),
				3 => (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.easternAmerica',
				),
				4 => (string) $this->translator->translate('//tuya-connector.cmd.install.answers.dataCentre.china'),
				5 => (string) $this->translator->translate('//tuya-connector.cmd.install.answers.dataCentre.india'),
			],
			0,
		);
		$question->setErrorMessage(
			(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer): Types\OpenApiEndpoint {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (
				$answer === (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.centralEurope',
				)
				|| $answer === '0'
			) {
				return Types\OpenApiEndpoint::EUROPE;
			}

			if (
				$answer === (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.westernEurope',
				)
				|| $answer === '1'
			) {
				return Types\OpenApiEndpoint::EUROPE_MS;
			}

			if (
				$answer === (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.westernAmerica',
				)
				|| $answer === '2'
			) {
				return Types\OpenApiEndpoint::AMERICA;
			}

			if (
				$answer === (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.easternAmerica',
				)
				|| $answer === '3'
			) {
				return Types\OpenApiEndpoint::AMERICA_AZURE;
			}

			if (
				$answer === (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.china',
				)
				|| $answer === '4'
			) {
				return Types\OpenApiEndpoint::CHINA;
			}

			if (
				$answer === (string) $this->translator->translate(
					'//tuya-connector.cmd.install.answers.dataCentre.india',
				)
				|| $answer === '5'
			) {
				return Types\OpenApiEndpoint::INDIA;
			}

			throw new Exceptions\Runtime(
				sprintf(
					(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\OpenApiEndpoint);

		return $answer;
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askConnectorUid(
		Style\SymfonyStyle $io,
		Entities\Connectors\Connector|null $connector = null,
	): string
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate('//tuya-connector.cmd.install.questions.provide.connector.uid'),
			$connector?->getUid(),
		);
		$question->setValidator(function (string|null $answer): string {
			if ($answer === '' || $answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			return $answer;
		});

		return strval($io->askQuestion($question));
	}

	private function askDeviceName(Style\SymfonyStyle $io, Entities\Devices\Device|null $device = null): string|null
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate('//tuya-connector.cmd.install.questions.provide.device.name'),
			$device?->getName(),
		);

		$name = $io->askQuestion($question);

		return strval($name) === '' ? null : strval($name);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichConnector(Style\SymfonyStyle $io): Entities\Connectors\Connector|null
	{
		$connectors = [];

		$findConnectorsQuery = new Queries\Entities\FindConnectors();

		$systemConnectors = $this->connectorsRepository->findAllBy(
			$findConnectorsQuery,
			Entities\Connectors\Connector::class,
		);
		usort(
			$systemConnectors,
			static fn (Entities\Connectors\Connector $a, Entities\Connectors\Connector $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		foreach ($systemConnectors as $connector) {
			$connectors[$connector->getIdentifier()] = $connector->getName() ?? $connector->getIdentifier();
		}

		if (count($connectors) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//tuya-connector.cmd.install.questions.select.item.connector'),
			array_values($connectors),
			count($connectors) === 1 ? 0 : null,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|int|null $answer) use ($connectors): Entities\Connectors\Connector {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($connectors))) {
				$answer = array_values($connectors)[$answer];
			}

			$identifier = array_search($answer, $connectors, true);

			if ($identifier !== false) {
				$findConnectorQuery = new Queries\Entities\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\Connectors\Connector::class,
				);

				if ($connector !== null) {
					return $connector;
				}
			}

			throw new Exceptions\Runtime(
				sprintf(
					(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$connector = $io->askQuestion($question);
		assert($connector instanceof Entities\Connectors\Connector);

		return $connector;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichDevice(
		Style\SymfonyStyle $io,
		Entities\Connectors\Connector $connector,
	): Entities\Devices\Device|null
	{
		$devices = [];

		$findDevicesQuery = new Queries\Entities\FindDevices();
		$findDevicesQuery->forConnector($connector);

		$connectorDevices = $this->devicesRepository->findAllBy(
			$findDevicesQuery,
			Entities\Devices\Device::class,
		);
		usort(
			$connectorDevices,
			static fn (Entities\Devices\Device $a, Entities\Devices\Device $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		foreach ($connectorDevices as $device) {
			$devices[$device->getIdentifier()] = $device->getName() ?? $device->getIdentifier();
		}

		if (count($devices) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//tuya-connector.cmd.install.questions.select.item.device'),
			array_values($devices),
			count($devices) === 1 ? 0 : null,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(
			function (string|int|null $answer) use ($connector, $devices): Entities\Devices\Device {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
							$answer,
						),
					);
				}

				if (array_key_exists($answer, array_values($devices))) {
					$answer = array_values($devices)[$answer];
				}

				$identifier = array_search($answer, $devices, true);

				if ($identifier !== false) {
					$findDeviceQuery = new Queries\Entities\FindDevices();
					$findDeviceQuery->byIdentifier($identifier);
					$findDeviceQuery->forConnector($connector);

					$device = $this->devicesRepository->findOneBy(
						$findDeviceQuery,
						Entities\Devices\Device::class,
					);

					if ($device !== null) {
						return $device;
					}
				}

				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//tuya-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			},
		);

		$device = $io->askQuestion($question);
		assert($device instanceof Entities\Devices\Device);

		return $device;
	}

}
