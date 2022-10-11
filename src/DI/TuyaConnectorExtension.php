<?php declare(strict_types = 1);

/**
 * TuyaConnectorExtension.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     DI
 * @since          0.13.0
 *
 * @date           24.08.22
 */

namespace FastyBird\TuyaConnector\DI;

use Doctrine\Persistence;
use FastyBird\DevicesModule\DI as DevicesModuleDI;
use FastyBird\TuyaConnector\API;
use FastyBird\TuyaConnector\Clients;
use FastyBird\TuyaConnector\Commands;
use FastyBird\TuyaConnector\Connector;
use FastyBird\TuyaConnector\Consumers;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Hydrators;
use FastyBird\TuyaConnector\Mappers;
use FastyBird\TuyaConnector\Schemas;
use FastyBird\TuyaConnector\Subscribers;
use Nette;
use Nette\DI;
use Nette\Schema;
use React\EventLoop;
use stdClass;
use function assert;
use const DIRECTORY_SEPARATOR;

/**
 * Tuya connector
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class TuyaConnectorExtension extends DI\CompilerExtension
{

	public const NAME = 'fbTuyaConnector';

	public static function register(
		Nette\Configurator $config,
		string $extensionName = self::NAME,
	): void
	{
		$config->onCompile[] = static function (
			Nette\Configurator $config,
			DI\Compiler $compiler,
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new TuyaConnectorExtension());
		};
	}

	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'loop' => Schema\Expect::anyOf(
				Schema\Expect::string(),
				Schema\Expect::type(DI\Definitions\Statement::class),
			)
				->nullable(),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$configuration = $this->getConfig();
		assert($configuration instanceof stdClass);

		if ($configuration->loop === null && $builder->getByType(EventLoop\LoopInterface::class) === null) {
			$builder->addDefinition($this->prefix('client.loop'), new DI\Definitions\ServiceDefinition())
				->setType(EventLoop\LoopInterface::class)
				->setFactory('React\EventLoop\Factory::create');
		}

		// Consumers
		$builder->addDefinition($this->prefix('consumer.discovery.cloudDevice'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\Messages\CloudDiscovery::class);

		$builder->addDefinition($this->prefix('consumer.discovery.localDevice'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\Messages\LocalDiscovery::class);

		$builder->addDefinition($this->prefix('consumer.discovery.status'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\Messages\Status::class);

		$builder->addDefinition($this->prefix('consumer.discovery.state'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\Messages\State::class);

		$builder->addDefinition($this->prefix('consumer.proxy'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\Messages::class)
			->setArguments([
				'consumers' => $builder->findByType(Consumers\Consumer::class),
			]);

		// API
		$builder->addDefinition($this->prefix('api.openApi.api'))
			->setType(API\OpenApiFactory::class);

		$builder->addDefinition($this->prefix('api.openApi.entityFactory'))
			->setType(API\EntityFactory::class);

		$builder->addDefinition($this->prefix('api.localApi.api'))
			->setType(API\LocalApiFactory::class);

		// Clients
		$builder->addFactoryDefinition($this->prefix('client.local'))
			->setImplement(Clients\LocalFactory::class)
			->getResultDefinition()
			->setType(Clients\Local::class);

		$builder->addFactoryDefinition($this->prefix('client.cloud'))
			->setImplement(Clients\CloudFactory::class)
			->getResultDefinition()
			->setType(Clients\Cloud::class);

		$builder->addFactoryDefinition($this->prefix('client.discover'))
			->setImplement(Clients\DiscoveryFactory::class)
			->getResultDefinition()
			->setType(Clients\Discovery::class);

		// Events subscribers
		$builder->addDefinition($this->prefix('subscribers.properties'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Properties::class);

		// API schemas
		$builder->addDefinition($this->prefix('schemas.connector.tuya'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\TuyaConnector::class);

		$builder->addDefinition($this->prefix('schemas.device.tuya'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\TuyaDevice::class);

		// API hydrators
		$builder->addDefinition($this->prefix('hydrators.connector.tuya'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\TuyaConnector::class);

		$builder->addDefinition($this->prefix('hydrators.device.tuya'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\TuyaDevice::class);

		// Helpers
		$builder->addDefinition($this->prefix('helpers.database'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Database::class);

		$builder->addDefinition($this->prefix('helpers.connector'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Connector::class);

		$builder->addDefinition($this->prefix('helpers.device'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Device::class);

		$builder->addDefinition($this->prefix('helpers.property'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Property::class);

		// Mappers
		$builder->addDefinition($this->prefix('mappers.dataPoint'), new DI\Definitions\ServiceDefinition())
			->setType(Mappers\DataPoint::class);

		// Service factory
		$builder->addFactoryDefinition($this->prefix('executor.factory'))
			->setImplement(Connector\ConnectorFactory::class)
			->addTag(
				DevicesModuleDI\DevicesModuleExtension::CONNECTOR_TYPE_TAG,
				Entities\TuyaConnector::CONNECTOR_TYPE,
			)
			->getResultDefinition()
			->setType(Connector\Connector::class)
			->setArguments([
				'clientsFactories' => $builder->findByType(Clients\ClientFactory::class),
			]);

		// Console commands
		$builder->addDefinition($this->prefix('commands.initialize'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Initialize::class);

		$builder->addDefinition($this->prefix('commands.discovery'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Discovery::class);

		$builder->addDefinition($this->prefix('commands.execute'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Execute::class);
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		/**
		 * Doctrine entities
		 */

		$ormAnnotationDriverService = $builder->getDefinition('nettrineOrmAnnotations.annotationDriver');

		if ($ormAnnotationDriverService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverService->addSetup(
				'addPaths',
				[[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Entities']],
			);
		}

		$ormAnnotationDriverChainService = $builder->getDefinitionByType(
			Persistence\Mapping\Driver\MappingDriverChain::class,
		);

		if ($ormAnnotationDriverChainService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverChainService->addSetup('addDriver', [
				$ormAnnotationDriverService,
				'FastyBird\TuyaConnector\Entities',
			]);
		}
	}

}
