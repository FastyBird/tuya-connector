<?php declare(strict_types = 1);

/**
 * Initialize.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Commands
 * @since          1.0.0
 *
 * @date           04.08.22
 */

namespace FastyBird\Connector\Tuya\Commands;

use Doctrine\DBAL;
use Doctrine\Persistence;
use FastyBird\Connector\Tuya\Entities;
use FastyBird\Connector\Tuya\Exceptions;
use FastyBird\Connector\Tuya\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Nette\Utils;
use Psr\Log;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;
use function array_search;
use function array_values;
use function count;
use function intval;
use function sprintf;
use function strval;

/**
 * Connector initialize command
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Initialize extends Console\Command\Command
{

	public const NAME = 'fb:tuya-connector:initialize';

	private const CHOICE_QUESTION_CREATE_CONNECTOR = 'Create new connector configuration';

	private const CHOICE_QUESTION_EDIT_CONNECTOR = 'Edit existing connector configuration';

	private const CHOICE_QUESTION_DELETE_CONNECTOR = 'Delete existing connector configuration';

	private const CHOICE_QUESTION_LOCAL_MODE = 'Local network mode';

	private const CHOICE_QUESTION_CLOUD_MODE = 'Cloud server mode';

	private const CHOICE_QUESTION_CENTRAL_EUROPE_DC = 'Central Europe';

	private const CHOICE_QUESTION_WESTERN_EUROPE_DC = 'Western Europe';

	private const CHOICE_QUESTION_WESTERN_AMERICA_DC = 'Western America';

	private const CHOICE_QUESTION_EASTERN_AMERICA_DC = 'Eastern America';

	private const CHOICE_QUESTION_CHINA_DC = 'China';

	private const CHOICE_QUESTION_INDIA_DC = 'India';

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly DevicesModels\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Connectors\ConnectorsManager $connectorsManager,
		private readonly DevicesModels\Connectors\Properties\PropertiesManager $propertiesManager,
		private readonly DevicesModels\Connectors\Controls\ControlsManager $controlsManager,
		private readonly Persistence\ManagerRegistry $managerRegistry,
		Log\LoggerInterface|null $logger = null,
		string|null $name = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 */
	protected function configure(): void
	{
		$this
			->setName(self::NAME)
			->setDescription('Tuya connector initialization')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption(
						'no-confirm',
						null,
						Input\InputOption::VALUE_NONE,
						'Do not ask for any confirmation',
					),
				]),
			);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title('Tuya connector - initialization');

		$io->note('This action will create|update connector configuration.');

		if ($input->getOption('no-confirm') === false) {
			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to continue?',
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if (!$continue) {
				return Console\Command\Command::SUCCESS;
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			'What would you like to do?',
			[
				0 => self::CHOICE_QUESTION_CREATE_CONNECTOR,
				1 => self::CHOICE_QUESTION_EDIT_CONNECTOR,
				2 => self::CHOICE_QUESTION_DELETE_CONNECTOR,
			],
		);

		$question->setErrorMessage('Selected answer: "%s" is not valid.');

		$whatToDo = $io->askQuestion($question);

		if ($whatToDo === self::CHOICE_QUESTION_CREATE_CONNECTOR) {
			$this->createNewConfiguration($io);

		} elseif ($whatToDo === self::CHOICE_QUESTION_EDIT_CONNECTOR) {
			$this->editExistingConfiguration($io);

		} elseif ($whatToDo === self::CHOICE_QUESTION_DELETE_CONNECTOR) {
			$this->deleteExistingConfiguration($io);
		}

		return Console\Command\Command::SUCCESS;
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 */
	private function createNewConfiguration(Style\SymfonyStyle $io): void
	{
		$mode = $this->askMode($io);

		$question = new Console\Question\Question('Provide connector identifier');

		$question->setValidator(function ($answer) {
			if ($answer !== null) {
				$findConnectorQuery = new DevicesQueries\FindConnectors();
				$findConnectorQuery->byIdentifier($answer);

				if ($this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\TuyaConnector::class,
				) !== null) {
					throw new Exceptions\Runtime('This identifier is already used');
				}
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'tuya-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				$findConnectorQuery = new DevicesQueries\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				if ($this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\TuyaConnector::class,
				) === null) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error('Connector identifier have to provided');

			return;
		}

		$question = new Console\Question\Question('Provide connector name');

		$name = $io->askQuestion($question);

		$accessId = $this->askAccessId($io);

		$accessSecret = $this->askAccessSecret($io);

		switch ($this->askOpenApiEndpoint($io)) {
			case 1:
				$dataCentre = Types\OpenApiEndpoint::ENDPOINT_EUROPE_MS;

				break;
			case 2:
				$dataCentre = Types\OpenApiEndpoint::ENDPOINT_AMERICA;

				break;
			case 3:
				$dataCentre = Types\OpenApiEndpoint::ENDPOINT_AMERICA_AZURE;

				break;
			case 4:
				$dataCentre = Types\OpenApiEndpoint::ENDPOINT_CHINA;

				break;
			case 5:
				$dataCentre = Types\OpenApiEndpoint::ENDPOINT_INDIA;

				break;
			default:
				$dataCentre = Types\OpenApiEndpoint::ENDPOINT_EUROPE;

				break;
		}

		$uid = null;

		if ($mode->equalsValue(Types\ClientMode::MODE_CLOUD)) {
			$uid = $this->askUid($io);
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->create(Utils\ArrayHash::from([
				'entity' => Entities\TuyaConnector::class,
				'identifier' => $identifier,
				'name' => $name === '' ? null : $name,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE,
				'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
				'value' => $mode->getValue(),
				'connector' => $connector,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID,
				'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
				'value' => $accessId,
				'connector' => $connector,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET,
				'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
				'value' => $accessSecret,
				'connector' => $connector,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_OPENPULSAR_ENDPOINT,
				'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
				'value' => $dataCentre,
				'connector' => $connector,
			]));

			if ($mode->equalsValue(Types\ClientMode::MODE_CLOUD)) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_UID,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'value' => $uid,
					'connector' => $connector,
				]));
			}

			$this->controlsManager->create(Utils\ArrayHash::from([
				'name' => Types\ConnectorControlName::NAME_REBOOT,
				'connector' => $connector,
			]));

			$this->controlsManager->create(Utils\ArrayHash::from([
				'name' => Types\ConnectorControlName::NAME_DISCOVER,
				'connector' => $connector,
			]));

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'New connector "%s" was successfully created',
				$connector->getName() ?? $connector->getIdentifier(),
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$io->error('Something went wrong, connector could not be created. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function editExistingConfiguration(Style\SymfonyStyle $io): void
	{
		$io->newLine();

		$connectors = [];

		$findConnectorsQuery = new DevicesQueries\FindConnectors();

		foreach ($this->connectorsRepository->findAllBy(
			$findConnectorsQuery,
			Entities\TuyaConnector::class,
		) as $connector) {
			if ($connector->getType() !== Entities\TuyaConnector::CONNECTOR_TYPE) {
				continue;
			}

			$connectors[$connector->getIdentifier()] = $connector->getIdentifier()
				. ($connector->getName() !== null ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			$io->warning('No Tuya connectors registered in system');

			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to create new Tuya connector configuration?',
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createNewConfiguration($io);
			}

			return;
		}

		$question = new Console\Question\ChoiceQuestion(
			'Please select connector to configure',
			array_values($connectors),
		);

		$question->setErrorMessage('Selected connector: "%s" is not valid.');

		$connectorIdentifier = array_search($io->askQuestion($question), $connectors, true);

		if ($connectorIdentifier === false) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert(
				'Could not read connector identifier from console answer',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
				],
			);

			return;
		}

		$findConnectorQuery = new DevicesQueries\FindConnectors();
		$findConnectorQuery->byIdentifier($connectorIdentifier);

		$connector = $this->connectorsRepository->findOneBy($findConnectorQuery, Entities\TuyaConnector::class);

		if ($connector === null) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert(
				'Connector was not found',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
				],
			);

			return;
		}

		$modeProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE);

		if ($modeProperty === null) {
			$changeMode = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to change connector devices support?',
				false,
			);

			$changeMode = (bool) $io->askQuestion($question);
		}

		$mode = null;

		if ($changeMode) {
			$mode = $this->askMode($io);
		}

		$question = new Console\Question\Question('Provide connector name', $connector->getName());

		$name = $io->askQuestion($question);

		$enabled = $connector->isEnabled();

		if ($connector->isEnabled()) {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to disable connector?',
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = false;
			}
		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to enable connector?',
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = true;
			}
		}

		$accessId = $accessSecret = null;

		$accessIdProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID);

		if ($accessIdProperty === null) {
			$changeAccessId = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to change connector cloud Access ID?',
				false,
			);

			$changeAccessId = (bool) $io->askQuestion($question);
		}

		if ($changeAccessId) {
			$accessId = $this->askAccessId($io);
		}

		$accessSecretProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET);

		if ($accessSecretProperty === null) {
			$changeAccessSecret = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to change connector cloud Access Secret?',
				false,
			);

			$changeAccessSecret = (bool) $io->askQuestion($question);
		}

		if ($changeAccessSecret) {
			$accessSecret = $this->askAccessSecret($io);
		}

		$uid = null;
		$uidProperty = null;

		if (
			(
				$modeProperty !== null
				&& $modeProperty->getValue() === Types\ClientMode::MODE_CLOUD
			) || (
				$mode !== null
				&& $mode->equalsValue(Types\ClientMode::MODE_CLOUD)
			)
		) {
			$uidProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_UID);

			if ($uidProperty === null) {
				$changeUid = true;

			} else {
				$question = new Console\Question\ConfirmationQuestion(
					'Do you want to change connector cloud user identifier?',
					false,
				);

				$changeUid = (bool) $io->askQuestion($question);
			}

			if ($changeUid) {
				$uid = $this->askUid($io);
			}
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->update($connector, Utils\ArrayHash::from([
				'name' => $name === '' ? null : $name,
				'enabled' => $enabled,
			]));

			if ($modeProperty === null) {
				if ($mode === null) {
					$mode = $this->askMode($io);
				}

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'value' => $mode->getValue(),
					'connector' => $connector,
				]));
			} elseif ($mode !== null) {
				$this->propertiesManager->update($modeProperty, Utils\ArrayHash::from([
					'value' => $mode->getValue(),
				]));
			}

			if ($accessIdProperty === null) {
				if ($accessId === null) {
					$accessId = $this->askAccessId($io);
				}

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
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
					$accessSecret = $this->askAccessSecret($io);
				}

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
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
					&& $modeProperty->getValue() === Types\ClientMode::MODE_CLOUD
				) || (
					$mode !== null
					&& $mode->equalsValue(Types\ClientMode::MODE_CLOUD)
				)
			) {
				if ($uidProperty === null) {
					if ($uid === null) {
						$uid = $this->askUid($io);
					}

					$this->propertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_UID,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
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
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'Connector "%s" was successfully updated',
				$connector->getName() ?? $connector->getIdentifier(),
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$io->error('Something went wrong, connector could not be updated. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 */
	private function deleteExistingConfiguration(Style\SymfonyStyle $io): void
	{
		$io->newLine();

		$connectors = [];

		$findConnectorsQuery = new DevicesQueries\FindConnectors();

		foreach ($this->connectorsRepository->findAllBy(
			$findConnectorsQuery,
			Entities\TuyaConnector::class,
		) as $connector) {
			if ($connector->getType() !== Entities\TuyaConnector::CONNECTOR_TYPE) {
				continue;
			}

			$connectors[$connector->getIdentifier()] = $connector->getIdentifier()
				. ($connector->getName() !== null ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			$io->info('No Tuya connectors registered in system');

			return;
		}

		$question = new Console\Question\ChoiceQuestion(
			'Please select connector to remove',
			array_values($connectors),
		);

		$question->setErrorMessage('Selected connector: "%s" is not valid.');

		$connectorIdentifier = array_search($io->askQuestion($question), $connectors, true);

		if ($connectorIdentifier === false) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert(
				'Connector identifier was not able to get from answer',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
				],
			);

			return;
		}

		$findConnectorQuery = new DevicesQueries\FindConnectors();
		$findConnectorQuery->byIdentifier($connectorIdentifier);

		$connector = $this->connectorsRepository->findOneBy($findConnectorQuery, Entities\TuyaConnector::class);

		if ($connector === null) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert(
				'Connector was not found',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
				],
			);

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			'Would you like to continue?',
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$this->connectorsManager->delete($connector);

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'Connector "%s" was successfully removed',
				$connector->getName() ?? $connector->getIdentifier(),
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_TUYA,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$io->error('Something went wrong, connector could not be removed. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @throws Exceptions\InvalidState
	 */
	private function askMode(Style\SymfonyStyle $io): Types\ClientMode
	{
		$question = new Console\Question\ChoiceQuestion(
			'In what mode should this connector communicate with devices?',
			[
				self::CHOICE_QUESTION_LOCAL_MODE,
				self::CHOICE_QUESTION_CLOUD_MODE,
			],
			0,
		);

		$question->setErrorMessage('Selected answer: "%s" is not valid.');

		$mode = $io->askQuestion($question);

		if ($mode === self::CHOICE_QUESTION_LOCAL_MODE) {
			return Types\ClientMode::get(Types\ClientMode::MODE_LOCAL);
		}

		if ($mode === self::CHOICE_QUESTION_CLOUD_MODE) {
			return Types\ClientMode::get(Types\ClientMode::MODE_CLOUD);
		}

		throw new Exceptions\InvalidState('Unknown connector mode selected');
	}

	private function askAccessId(Style\SymfonyStyle $io): string
	{
		$question = new Console\Question\Question('Provide cloud authentication Access ID');

		return strval($io->askQuestion($question));
	}

	private function askAccessSecret(Style\SymfonyStyle $io): string
	{
		$question = new Console\Question\Question('Provide cloud authentication Access Secret');

		return strval($io->askQuestion($question));
	}

	private function askOpenApiEndpoint(Style\SymfonyStyle $io): int
	{
		$question = new Console\Question\ChoiceQuestion(
			'Provide which cloud data center you are using?',
			[
				0 => self::CHOICE_QUESTION_CENTRAL_EUROPE_DC,
				1 => self::CHOICE_QUESTION_WESTERN_EUROPE_DC,
				2 => self::CHOICE_QUESTION_WESTERN_AMERICA_DC,
				3 => self::CHOICE_QUESTION_EASTERN_AMERICA_DC,
				4 => self::CHOICE_QUESTION_CHINA_DC,
				5 => self::CHOICE_QUESTION_INDIA_DC,
			],
			0,
		);

		$question->setErrorMessage('Selected answer: "%s" is not valid.');

		return intval($io->askQuestion($question));
	}

	private function askUid(Style\SymfonyStyle $io): string
	{
		$question = new Console\Question\Question('Provide cloud user identification');

		return strval($io->askQuestion($question));
	}

	/**
	 * @throws Exceptions\Runtime
	 */
	private function getOrmConnection(): DBAL\Connection
	{
		$connection = $this->managerRegistry->getConnection();

		if ($connection instanceof DBAL\Connection) {
			return $connection;
		}

		throw new Exceptions\Runtime('Entity manager could not be loaded');
	}

}
