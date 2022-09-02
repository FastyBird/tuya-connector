<?php declare(strict_types = 1);

/**
 * Initialize.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Commands
 * @since          0.34.0
 *
 * @date           04.08.22
 */

namespace FastyBird\TuyaConnector\Commands;

use Doctrine\DBAL;
use Doctrine\Persistence;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Exceptions;
use FastyBird\TuyaConnector\Types;
use Nette\Utils;
use Psr\Log;
use RuntimeException;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;

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

	private const CHOICE_QUESTION_CREATE_CONNECTOR = 'Create new connector configuration';
	private const CHOICE_QUESTION_EDIT_CONNECTOR = 'Edit existing connector configuration';
	private const CHOICE_QUESTION_DELETE_CONNECTOR = 'Delete existing connector configuration';

	private const CHOICE_QUESTION_LOCAL_MODE = 'Local network';
	private const CHOICE_QUESTION_CLOUD_MODE = 'Cloud';

	/** @var DevicesModuleModels\Connectors\IConnectorsRepository */
	private DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository;

	/** @var DevicesModuleModels\Connectors\IConnectorsManager */
	private DevicesModuleModels\Connectors\IConnectorsManager $connectorsManager;

	/** @var DevicesModuleModels\Connectors\Properties\IPropertiesRepository */
	private DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesRepository;

	/** @var DevicesModuleModels\Connectors\Properties\IPropertiesManager */
	private DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesManager;

	/** @var DevicesModuleModels\Connectors\Controls\IControlsManager */
	private DevicesModuleModels\Connectors\Controls\IControlsManager $controlsManager;

	/** @var DevicesModuleModels\DataStorage\IConnectorsRepository */
	private DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsDataStorageRepository;

	/** @var Persistence\ManagerRegistry */
	private Persistence\ManagerRegistry $managerRegistry;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository
	 * @param DevicesModuleModels\Connectors\IConnectorsManager $connectorsManager
	 * @param DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesRepository
	 * @param DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesManager
	 * @param DevicesModuleModels\Connectors\Controls\IControlsManager $controlsManager
	 * @param DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsDataStorageRepository
	 * @param Persistence\ManagerRegistry $managerRegistry
	 * @param Log\LoggerInterface|null $logger
	 * @param string|null $name
	 */
	public function __construct(
		DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository,
		DevicesModuleModels\Connectors\IConnectorsManager $connectorsManager,
		DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesRepository,
		DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesManager,
		DevicesModuleModels\Connectors\Controls\IControlsManager $controlsManager,
		DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsDataStorageRepository,
		Persistence\ManagerRegistry $managerRegistry,
		?Log\LoggerInterface $logger = null,
		?string $name = null
	) {
		$this->connectorsRepository = $connectorsRepository;
		$this->connectorsManager = $connectorsManager;
		$this->propertiesRepository = $propertiesRepository;
		$this->propertiesManager = $propertiesManager;
		$this->controlsManager = $controlsManager;

		$this->connectorsDataStorageRepository = $connectorsDataStorageRepository;

		$this->managerRegistry = $managerRegistry;

		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function configure(): void
	{
		$this
			->setName('fb:tuya-connector:initialize')
			->setDescription('Tuya connector initialization')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption('no-confirm', null, Input\InputOption::VALUE_NONE, 'Do not ask for any confirmation'),
				])
			);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBAL\Exception
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title('Tuya connector - initialization');

		$io->note('This action will create|update connector configuration.');

		if (!$input->getOption('no-confirm')) {
			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to continue?',
				false
			);

			$continue = $io->askQuestion($question);

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
			]
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
	 * @param Style\SymfonyStyle $io
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function createNewConfiguration(Style\SymfonyStyle $io): void
	{
		$mode = $this->askMode($io);

		$question = new Console\Question\Question('Provide connector identifier');

		$question->setValidator(function ($answer) {
			if ($answer !== null && $this->connectorsDataStorageRepository->findByIdentifier($answer) !== null) {
				throw new RuntimeException('This identifier is already used');
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'tuya-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				if ($this->connectorsDataStorageRepository->findByIdentifier($identifier) === null) {
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

		$accessId = $accessSecret = $uid = null;

		if ($mode->equalsValue(Types\ClientMode::MODE_CLOUD)) {
			$accessId = $this->askAccessId($io);

			$accessSecret = $this->askAccessSecret($io);

			$uid = $this->askUid($io);
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->create(Utils\ArrayHash::from([
				'entity'     => Entities\TuyaConnector::class,
				'identifier' => $identifier,
				'name'       => $name === '' ? null : $name,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
				'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE,
				'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
				'value'      => $mode->getValue(),
				'connector'  => $connector,
			]));

			if ($mode->equalsValue(Types\ClientMode::MODE_CLOUD)) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID,
					'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
					'value'      => $accessId,
					'connector'  => $connector,
				]));

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET,
					'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
					'value'      => $accessSecret,
					'connector'  => $connector,
				]));

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_UID,
					'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
					'value'      => $uid,
					'connector'  => $connector,
				]));
			}

			$this->controlsManager->create(Utils\ArrayHash::from([
				'name'      => Types\ConnectorControlName::NAME_REBOOT,
				'connector' => $connector,
			]));

			$this->controlsManager->create(Utils\ArrayHash::from([
				'name'      => Types\ConnectorControlName::NAME_DISCOVER,
				'connector' => $connector,
			]));

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'New connector "%s" was successfully created',
				$connector->getName() ?? $connector->getIdentifier()
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error('An unhandled error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'      => 'initialize-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, connector could not be created. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function editExistingConfiguration(Style\SymfonyStyle $io): void
	{
		$io->newLine();

		$connectors = [];

		foreach ($this->connectorsDataStorageRepository as $connector) {
			if ($connector->getType() !== Entities\TuyaConnector::CONNECTOR_TYPE) {
				continue;
			}

			$connectors[$connector->getIdentifier()] = $connector->getIdentifier() . ($connector->getName() ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			$io->warning('No Tuya connectors registered in system');

			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to create new Tuya connector configuration?',
				false
			);

			$continue = $io->askQuestion($question);

			if ($continue) {
				$this->createNewConfiguration($io);
			}

			return;
		}

		$question = new Console\Question\ChoiceQuestion(
			'Please select connector to configure',
			array_values($connectors)
		);

		$question->setErrorMessage('Selected connector: "%s" is not valid.');

		$connectorIdentifier = array_search($io->askQuestion($question), $connectors);

		if ($connectorIdentifier === false) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector identifier was not able to get from answer', [
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
		$findConnectorQuery->byIdentifier($connectorIdentifier);

		$connector = $this->connectorsRepository->findOneBy($findConnectorQuery);

		if ($connector === null) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector was not found', [
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$findPropertyQuery = new DevicesModuleQueries\FindConnectorPropertiesQuery();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE);

		$modeProperty = $this->propertiesRepository->findOneBy($findPropertyQuery);

		if ($modeProperty === null) {
			$changeMode = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to change connector devices support?',
				false
			);

			$changeMode = $io->askQuestion($question);
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
				false
			);

			if ($io->askQuestion($question)) {
				$enabled = false;
			}
		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to enable connector?',
				false
			);

			if ($io->askQuestion($question)) {
				$enabled = true;
			}
		}

		$accessIdProperty = $accessSecretProperty = $uidProperty = null;
		$accessId = $accessSecret = $uid = null;

		if (
			(
				$modeProperty !== null
				&& $modeProperty->getValue() === Types\ClientMode::MODE_CLOUD
			) || (
				$mode !== null
				&& $mode->equalsValue(Types\ClientMode::MODE_CLOUD)
			)
		) {
			$findPropertyQuery = new DevicesModuleQueries\FindConnectorPropertiesQuery();
			$findPropertyQuery->forConnector($connector);
			$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID);

			$accessIdProperty = $this->propertiesRepository->findOneBy($findPropertyQuery);

			if ($accessIdProperty === null) {
				$changeAccessId = true;

			} else {
				$question = new Console\Question\ConfirmationQuestion(
					'Do you want to change connector cloud Access ID?',
					false
				);

				$changeAccessId = $io->askQuestion($question);
			}

			if ($changeAccessId) {
				$accessId = $this->askAccessId($io);
			}

			$findPropertyQuery = new DevicesModuleQueries\FindConnectorPropertiesQuery();
			$findPropertyQuery->forConnector($connector);
			$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET);

			$accessSecretProperty = $this->propertiesRepository->findOneBy($findPropertyQuery);

			if ($accessSecretProperty === null) {
				$changeAccessSecret = true;

			} else {
				$question = new Console\Question\ConfirmationQuestion(
					'Do you want to change connector cloud Access Secret?',
					false
				);

				$changeAccessSecret = $io->askQuestion($question);
			}

			if ($changeAccessSecret) {
				$accessSecret = $this->askAccessSecret($io);
			}

			$findPropertyQuery = new DevicesModuleQueries\FindConnectorPropertiesQuery();
			$findPropertyQuery->forConnector($connector);
			$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::IDENTIFIER_UID);

			$uidProperty = $this->propertiesRepository->findOneBy($findPropertyQuery);

			if ($uidProperty === null) {
				$changeUid = true;

			} else {
				$question = new Console\Question\ConfirmationQuestion(
					'Do you want to change connector cloud user identifier?',
					false
				);

				$changeUid = $io->askQuestion($question);
			}

			if ($changeUid) {
				$uid = $this->askUid($io);
			}
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->update($connector, Utils\ArrayHash::from([
				'name'    => $name === '' ? null : $name,
				'enabled' => $enabled,
			]));

			if ($modeProperty === null) {
				if ($mode === null) {
					$mode = $this->askMode($io);
				}

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE,
					'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
					'value'      => $mode->getValue(),
					'connector'  => $connector,
				]));
			} elseif ($mode !== null) {
				$this->propertiesManager->update($modeProperty, Utils\ArrayHash::from([
					'value' => $mode->getValue(),
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
				if ($accessIdProperty === null) {
					if ($accessId === null) {
						$accessId = $this->askAccessId($io);
					}

					$this->propertiesManager->create(Utils\ArrayHash::from([
						'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
						'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_ID,
						'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
						'value'      => $accessId,
						'connector'  => $connector,
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
						'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
						'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_ACCESS_SECRET,
						'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
						'value'      => $accessSecret,
						'connector'  => $connector,
					]));
				} elseif ($accessSecret !== null) {
					$this->propertiesManager->update($accessSecretProperty, Utils\ArrayHash::from([
						'value' => $accessSecret,
					]));
				}

				if ($uidProperty === null) {
					if ($uid === null) {
						$uid = $this->askUid($io);
					}

					$this->propertiesManager->create(Utils\ArrayHash::from([
						'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
						'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_UID,
						'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
						'value'      => $uid,
						'connector'  => $connector,
					]));
				} elseif ($uid !== null) {
					$this->propertiesManager->update($uidProperty, Utils\ArrayHash::from([
						'value' => $uid,
					]));
				}
			} else {
				if ($accessIdProperty !== null) {
					$this->propertiesManager->delete($accessIdProperty);
				}

				if ($accessSecretProperty !== null) {
					$this->propertiesManager->delete($accessSecretProperty);
				}

				if ($uidProperty !== null) {
					$this->propertiesManager->delete($uidProperty);
				}
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'Connector "%s" was successfully updated',
				$connector->getName() ?? $connector->getIdentifier()
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error('An unhandled error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'      => 'initialize-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, connector could not be updated. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function deleteExistingConfiguration(Style\SymfonyStyle $io): void
	{
		$io->newLine();

		$connectors = [];

		foreach ($this->connectorsDataStorageRepository as $connector) {
			if ($connector->getType() !== Entities\TuyaConnector::CONNECTOR_TYPE) {
				continue;
			}

			$connectors[$connector->getIdentifier()] = $connector->getIdentifier() . ($connector->getName() ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			$io->info('No Tuya connectors registered in system');

			return;
		}

		$question = new Console\Question\ChoiceQuestion(
			'Please select connector to remove',
			array_values($connectors)
		);

		$question->setErrorMessage('Selected connector: "%s" is not valid.');

		$connectorIdentifier = array_search($io->askQuestion($question), $connectors);

		if ($connectorIdentifier === false) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector identifier was not able to get from answer', [
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
		$findConnectorQuery->byIdentifier($connectorIdentifier);

		$connector = $this->connectorsRepository->findOneBy($findConnectorQuery);

		if ($connector === null) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector was not found', [
				'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			'Would you like to continue?',
			false
		);

		$continue = $io->askQuestion($question);

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
				$connector->getName() ?? $connector->getIdentifier()
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error('An unhandled error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'      => 'initialize-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, connector could not be removed. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return Types\ClientMode
	 */
	private function askMode(Style\SymfonyStyle $io): Types\ClientMode
	{
		$question = new Console\Question\ChoiceQuestion(
			'What type of Tuya devices should this connector handle?',
			[
				self::CHOICE_QUESTION_LOCAL_MODE,
				self::CHOICE_QUESTION_CLOUD_MODE,
			],
			0
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

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return string
	 */
	private function askAccessId(Style\SymfonyStyle $io): string
	{
		$question = new Console\Question\Question('Provide cloud authentication Access ID');

		return strval($io->askQuestion($question));
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return string
	 */
	private function askAccessSecret(Style\SymfonyStyle $io): string
	{
		$question = new Console\Question\Question('Provide cloud authentication Access Secret');

		return strval($io->askQuestion($question));
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return string
	 */
	private function askUid(Style\SymfonyStyle $io): string
	{
		$question = new Console\Question\Question('Provide cloud user identification');

		return strval($io->askQuestion($question));
	}

	/**
	 * @return DBAL\Connection
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
