<?php declare(strict_types = 1);

/**
 * Execute.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Commands
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\Commands;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata;
use FastyBird\TuyaConnector\Entities;
use Psr\Log;
use Ramsey\Uuid;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;

/**
 * Connector execute command
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Execute extends Console\Command\Command
{

	/** @var DevicesModuleModels\DataStorage\IConnectorsRepository */
	private DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsRepository;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsRepository
	 * @param Log\LoggerInterface|null $logger
	 * @param string|null $name
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsRepository,
		?Log\LoggerInterface $logger = null,
		?string $name = null
	) {
		$this->connectorsRepository = $connectorsRepository;

		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function configure(): void
	{
		$this
			->setName('fb:tuya-connector:execute')
			->setDescription('Tuya connector service')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption('connector', 'c', Input\InputOption::VALUE_OPTIONAL, 'Run devices module connector', true),
					new Input\InputOption('no-confirm', null, Input\InputOption::VALUE_NONE, 'Do not ask for any confirmation'),
				])
			);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Console\Exception\ExceptionInterface
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$symfonyApp = $this->getApplication();

		if ($symfonyApp === null) {
			return Console\Command\Command::FAILURE;
		}

		$io = new Style\SymfonyStyle($input, $output);

		$io->title('Tuya connector - service');

		$io->note('This action will run connector service.');

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

		if (
			$input->hasOption('connector')
			&& is_string($input->getOption('connector'))
			&& $input->getOption('connector') !== ''
		) {
			$connectorId = $input->getOption('connector');

			if (Uuid\Uuid::isValid($connectorId)) {
				$connector = $this->connectorsRepository->findById(Uuid\Uuid::fromString($connectorId));
			} else {
				$connector = $this->connectorsRepository->findByIdentifier($connectorId);
			}

			if ($connector === null) {
				$io->warning('Connector was not found in system');

				return Console\Command\Command::FAILURE;
			}
		} else {
			$connectors = [];

			foreach ($this->connectorsRepository as $connector) {
				if ($connector->getType() !== Entities\TuyaConnector::CONNECTOR_TYPE) {
					continue;
				}

				$connectors[$connector->getIdentifier()] = $connector->getIdentifier() . $connector->getName() ? ' [' . $connector->getName() . ']' : '';
			}

			if (count($connectors) === 0) {
				$io->warning('No connectors registered in system');

				return Console\Command\Command::FAILURE;
			}

			if (count($connectors) === 1) {
				$connectorIdentifier = array_key_first($connectors);

				$connector = $this->connectorsRepository->findByIdentifier($connectorIdentifier);

				if ($connector === null) {
					$io->warning('Connector was not found in system');

					return Console\Command\Command::FAILURE;
				}

				if (!$input->getOption('no-confirm')) {
					$question = new Console\Question\ConfirmationQuestion(
						sprintf('Would you like to execute "%s" connector', $connector->getName() ?? $connector->getIdentifier()),
						false
					);

					if (!$io->askQuestion($question)) {
						return Console\Command\Command::SUCCESS;
					}
				}
			} else {
				$question = new Console\Question\ChoiceQuestion(
					'Please select connector to execute',
					array_values($connectors)
				);

				$question->setErrorMessage('Selected connector: %s is not valid.');

				$connectorIdentifierKey = array_search($io->askQuestion($question), $connectors);

				if ($connectorIdentifierKey === false) {
					$io->error('Something went wrong, connector could not be loaded');

					$this->logger->alert('Connector identifier was not able to get from answer', [
						'source' => Metadata\Constants::MODULE_DEVICES_SOURCE,
						'type'   => 'execute-cmd',
					]);

					return Console\Command\Command::FAILURE;
				}

				$connector = $this->connectorsRepository->findByIdentifier($connectorIdentifierKey);
			}

			if ($connector === null) {
				$io->error('Something went wrong, connector could not be loaded');

				$this->logger->alert('Connector was not found', [
					'source' => Metadata\Constants::MODULE_DEVICES_SOURCE,
					'type'   => 'execute-cmd',
				]);

				return Console\Command\Command::FAILURE;
			}
		}

		if (!$connector->isEnabled()) {
			$io->warning('Connector is disabled. Disabled connector could not be executed');

			return Console\Command\Command::SUCCESS;
		}

		$serviceCmd = $symfonyApp->find('fb:devices-module:service');

		$result = $serviceCmd->run(new Input\ArrayInput([
			'--connector'  => $connector->getId()->toString(),
			'--no-confirm' => true,
			'--quiet'      => true,
		]), $output);

		if ($result !== Console\Command\Command::SUCCESS) {
			$io->error('Something went wrong, service could not be processed.');

			return Console\Command\Command::FAILURE;
		}

		return Console\Command\Command::SUCCESS;
	}

}