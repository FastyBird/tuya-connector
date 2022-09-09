<?php declare(strict_types = 1);

/**
 * Local.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 * @since          0.13.0
 *
 * @date           25.08.22
 */

namespace FastyBird\TuyaConnector\Clients;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\TuyaConnector\API;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Helpers;
use FastyBird\TuyaConnector\Types;
use Nette;
use Psr\Log;
use Throwable;

/**
 * Local devices client
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Local implements Client
{

	use Nette\SmartObject;

	/** @var Array<string, API\LocalApi> */
	private array $devicesClients = [];

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var API\LocalApiFactory */
	private API\LocalApiFactory $localApiFactory;

	/** @var Helpers\Device */
	private Helpers\Device $deviceHelper;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Helpers\Device $deviceHelper
	 * @param API\LocalApiFactory $localApiFactory
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Helpers\Device $deviceHelper,
		API\LocalApiFactory $localApiFactory,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->localApiFactory = $localApiFactory;
		$this->deviceHelper = $deviceHelper;
		$this->devicesRepository = $devicesRepository;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $deviceItem) {
			$client = $this->localApiFactory->create(
				$deviceItem->getIdentifier(),
				null,
				strval($this->deviceHelper->getConfiguration(
					$deviceItem->getId(),
					Types\DevicePropertyIdentifier::get(Types\DevicePropertyIdentifier::IDENTIFIER_LOCAL_KEY)
				)),
				strval($this->deviceHelper->getConfiguration(
					$deviceItem->getId(),
					Types\DevicePropertyIdentifier::get(Types\DevicePropertyIdentifier::IDENTIFIER_IP_ADDRESS)
				)),
				Types\DeviceProtocolVersion::get(strval($this->deviceHelper->getConfiguration(
					$deviceItem->getId(),
					Types\DevicePropertyIdentifier::get(Types\DevicePropertyIdentifier::IDENTIFIER_PROTOCOL_VERSION)
				))),
			);

			$this->devicesClients[$deviceItem->getId()->toString()] = $client;

			$client->on('message', function (Entities\API\Entity $message): void {
				var_dump('RECEIVED');
				var_dump($message->toArray());
			});

			$client->connect()
				->then(function () use ($deviceItem): void {
					$this->logger->debug(
						'Connected to device',
						[
							'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'      => 'local-client',
							'device'    => [
								'id' => $deviceItem->getId()->toString(),
							],
						]
					);
				})
				->otherwise(function (Throwable $ex) use ($deviceItem): void {
					$this->logger->error(
						'Could not establish connection with device via local protocol',
						[
							'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'      => 'local-client',
							'device'    => [
								'id' => $deviceItem->getId()->toString(),
							],
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
						]
					);
				});
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		foreach ($this->devicesClients as $client) {
			$client->disconnect();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

}
