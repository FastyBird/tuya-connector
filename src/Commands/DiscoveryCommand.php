<?php declare(strict_types = 1);

/**
 * DiscoveryCommand.php
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

use DateTimeInterface;
use FastyBird\DateTimeFactory;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\TuyaConnector\Clients;
use FastyBird\TuyaConnector\Consumers;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Events;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Psr\Log;
use Ramsey\Uuid;
use React\EventLoop;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Symfony\Component\EventDispatcher;
use Throwable;

/**
 * Connector devices discovery command
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DiscoveryCommand extends Console\Command\Command implements EventDispatcher\EventSubscriberInterface
{

	private const DISCOVERY_WAITING_INTERVAL = 30.0;
	private const DISCOVERY_MAX_PROCESSING_INTERVAL = 30.0;

	private const QUEUE_PROCESSING_INTERVAL = 0.01;

	/** @var DateTimeInterface|null */
	private ?DateTimeInterface $executedTime = null;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $consumerTimer;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $progressBarTimer;

	/** @var Clients\DiscoveryClient|null */
	private ?Clients\DiscoveryClient $client = null;

	/** @var Clients\DiscoveryClientFactory */
	private Clients\DiscoveryClientFactory $clientFactory;

	/** @var Helpers\ConnectorHelper */
	private Helpers\ConnectorHelper $connectorHelper;

	/** @var Helpers\DeviceHelper */
	private Helpers\DeviceHelper $deviceHelper;

	/** @var Consumers\Consumer */
	private Consumers\Consumer $consumer;

	/** @var DevicesModuleModels\DataStorage\IConnectorsRepository */
	private DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsRepository;

	/** @var DevicesModuleModels\Devices\IDevicesRepository */
	private DevicesModuleModels\Devices\IDevicesRepository $devicesRepository;

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/**
	 * @param Clients\DiscoveryClientFactory $clientFactory
	 * @param Helpers\ConnectorHelper $connectorHelper
	 * @param Helpers\DeviceHelper $deviceHelper
	 * @param Consumers\Consumer $consumer
	 * @param DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsRepository
	 * @param DevicesModuleModels\Devices\IDevicesRepository $devicesRepository
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 * @param string|null $name
	 */
	public function __construct(
		Clients\DiscoveryClientFactory $clientFactory,
		Helpers\ConnectorHelper $connectorHelper,
		Helpers\DeviceHelper $deviceHelper,
		Consumers\Consumer $consumer,
		DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsRepository,
		DevicesModuleModels\Devices\IDevicesRepository $devicesRepository,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null,
		?string $name = null
	) {
		$this->clientFactory = $clientFactory;

		$this->connectorHelper = $connectorHelper;
		$this->deviceHelper = $deviceHelper;
		$this->consumer = $consumer;

		$this->connectorsRepository = $connectorsRepository;
		$this->devicesRepository = $devicesRepository;

		$this->dateTimeFactory = $dateTimeFactory;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			Events\DiscoveryFinishedEvent::class => 'discoveryFinished',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function configure(): void
	{
		$this
			->setName('fb:tuya-connector:discover')
			->setDescription('Tuya connector devices discovery')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption('connector', 'c', Input\InputOption::VALUE_OPTIONAL, 'Run devices module connector', true),
					new Input\InputOption('no-confirm', null, Input\InputOption::VALUE_NONE, 'Do not ask for any confirmation'),
				])
			);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title('Tuya connector - discovery');

		$io->note('This action will run connector devices discovery.');

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
				if ($connector->getType() !== Entities\TuyaConnectorEntity::CONNECTOR_TYPE) {
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
						sprintf('Would you like to discover devices with "%s" connector', $connector->getName() ?? $connector->getIdentifier()),
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

				$connectorIdentifier = array_search($io->askQuestion($question), $connectors);

				if ($connectorIdentifier === false) {
					$io->error('Something went wrong, connector could not be loaded');

					$this->logger->alert('Connector identifier was not able to get from answer', [
						'source' => Metadata\Constants::MODULE_DEVICES_SOURCE,
						'type'   => 'discovery-cmd',
					]);

					return Console\Command\Command::FAILURE;
				}

				$connector = $this->connectorsRepository->findByIdentifier($connectorIdentifier);
			}

			if ($connector === null) {
				$io->error('Something went wrong, connector could not be loaded');

				$this->logger->alert('Connector was not found', [
					'source' => Metadata\Constants::MODULE_DEVICES_SOURCE,
					'type'   => 'discovery-cmd',
				]);

				return Console\Command\Command::FAILURE;
			}
		}

		if (!$connector->isEnabled()) {
			$io->warning('Connector is disabled. Disabled connector could not be executed');

			return Console\Command\Command::SUCCESS;
		}

		$mode = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifierType::get(Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE)
		);

		if ($mode === null) {
			$io->error('Connector client mode is not configured');

			return Console\Command\Command::FAILURE;
		}

		$this->client = $this->clientFactory->create($connector);

		$progressBar = new Console\Helper\ProgressBar(
			$output,
			intval(self::DISCOVERY_WAITING_INTERVAL * 60)
		);

		$progressBar->setFormat('[%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %');

		try {
			$this->eventLoop->addSignal(SIGINT, function (int $signal) use ($io): void {
				$this->logger->info('Stopping Tuya connector discovery...', [
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'discovery-cmd',
				]);

				$io->info('Stopping Tuya connector discovery...');

				$this->client?->disconnect();

				$this->checkAndTerminate();
			});

			$this->eventLoop->futureTick(function () use ($io, $progressBar): void {
				$this->logger->info('Starting Tuya connector discovery...', [
					'source' => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'   => 'discovery-cmd',
				]);

				$io->info('Starting Tuya connector discovery...');

				$progressBar->start();

				$this->executedTime = $this->dateTimeFactory->getNow();

				$this->client?->discover();
			});

			$this->consumerTimer = $this->eventLoop->addPeriodicTimer(
				self::QUEUE_PROCESSING_INTERVAL,
				function (): void {
					$this->consumer->consume();
				}
			);

			$this->progressBarTimer = $this->eventLoop->addPeriodicTimer(
				0.1,
				function () use ($progressBar): void {
					$progressBar->advance();
				}
			);

			$this->eventLoop->addTimer(
				self::DISCOVERY_WAITING_INTERVAL,
				function (): void {
					$this->client?->disconnect();

					$this->checkAndTerminate();
				}
			);

			$this->eventLoop->run();

			$progressBar->finish();

			$io->newLine();

			$findDevicesQuery = new DevicesModuleQueries\FindDevicesQuery();
			$findDevicesQuery->byConnectorId($connector->getId());

			$devices = $this->devicesRepository->findAllBy($findDevicesQuery);

			$table = new Console\Helper\Table($output);
			$table->setHeaders([
				'#',
				'ID',
				'Name',
				'Type',
				'IP address',
			]);

			$foundDevices = 0;

			foreach ($devices as $device) {
				$createdAt = $device->getCreatedAt();

				if (
					$createdAt !== null
					&& $this->executedTime !== null
					&& $createdAt->getTimestamp() > $this->executedTime->getTimestamp()
				) {
					$foundDevices++;

					$ipAddress = $this->deviceHelper->getConfiguration(
						$device->getId(),
						Types\DevicePropertyIdentifierType::get(Types\DevicePropertyIdentifierType::IDENTIFIER_IP_ADDRESS)
					);

					$table->addRow([
						$foundDevices,
						$device->getPlainId(),
						$device->getName() ?? $device->getIdentifier(),
						'N/A',
						is_string($ipAddress) ? $ipAddress : 'N/A',
					]);
				}
			}

			if ($foundDevices > 0) {
				$io->newLine();

				$io->info(sprintf('Found %d new devices', $foundDevices));

				$table->render();

				$io->newLine();

			} else {
				$io->info('No devices were found');
			}

			$io->success('Devices discovery was successfully finished');

			return Console\Command\Command::SUCCESS;

		} catch (DevicesModuleExceptions\TerminateException $ex) {
			$this->logger->error('An error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'      => 'discovery-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, discovery could not be finished. Error was logged.');

			$this->client->disconnect();

			$this->eventLoop->stop();

			return Console\Command\Command::FAILURE;

		} catch (Throwable $ex) {
			$this->logger->error('An unhandled error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
				'type'      => 'discovery-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, discovery could not be finished. Error was logged.');

			$this->client->disconnect();

			$this->eventLoop->stop();

			return Console\Command\Command::FAILURE;
		}
	}

	/**
	 * @param Events\DiscoveryFinishedEvent $event
	 *
	 * @return void
	 */
	private function discoveryFinished(Events\DiscoveryFinishedEvent $event): void
	{
		$this->client?->disconnect();

		$this->checkAndTerminate();
	}

	/**
	 * @return void
	 */
	private function checkAndTerminate(): void
	{
		if ($this->consumer->isEmpty()) {
			if ($this->consumerTimer !== null) {
				$this->eventLoop->cancelTimer($this->consumerTimer);
			}

			if ($this->progressBarTimer !== null) {
				$this->eventLoop->cancelTimer($this->progressBarTimer);
			}

			$this->eventLoop->stop();

		} else {
			if (
				$this->executedTime !== null
				&& $this->dateTimeFactory->getNow()
					->getTimestamp() - $this->executedTime->getTimestamp() > self::DISCOVERY_MAX_PROCESSING_INTERVAL
			) {
				$this->logger->error('Discovery exceeded reserved time and have been terminated', [
					'source' => Metadata\Constants::MODULE_DEVICES_SOURCE,
					'type'   => 'discovery-cmd',
				]);

				if ($this->consumerTimer !== null) {
					$this->eventLoop->cancelTimer($this->consumerTimer);
				}

				if ($this->progressBarTimer !== null) {
					$this->eventLoop->cancelTimer($this->progressBarTimer);
				}

				$this->eventLoop->stop();

				return;
			}

			$this->eventLoop->addTimer(
				self::DISCOVERY_WAITING_INTERVAL,
				function (): void {
					$this->checkAndTerminate();
				}
			);
		}
	}

}
