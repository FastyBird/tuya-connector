<?php declare(strict_types = 1);

/**
 * Cloud.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 * @since          0.13.0
 *
 * @date           25.08.22
 */

namespace FastyBird\TuyaConnector\API;

use FastyBird\DateTimeFactory;
use FastyBird\Metadata;
use FastyBird\Metadata\Schemas as MetadataSchemas;
use FastyBird\TuyaConnector;
use FastyBird\TuyaConnector\Entities;
use FastyBird\TuyaConnector\Exceptions;
use FastyBird\TuyaConnector\Types;
use GuzzleHttp;
use Nette;
use Nette\Utils;
use Psr\Http\Message;
use Psr\Log;
use React\Promise;
use Throwable;

/**
 * OpenAPI interface
 *
 * @package        FastyBird:TuyaConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class OpenApi
{

	use Nette\SmartObject;

	private const VERSION = '0.1.0';

	private const TUYA_ERROR_CODE_TOKEN_INVALID = 1010;

	private const ACCESS_TOKEN_API_ENDPOINT = '/v1.0/token';
	private const REFRESH_TOKEN_API_ENDPOINT = '/v1.0/token/%s';

	private const USER_DEVICES_API_ENDPOINT = '/v1.0/users/%s/devices';
	private const USER_DEVICE_DETAIL_API_ENDPOINT = '/v1.0/devices/%s';
	private const USER_DEVICES_FACTORY_INFOS_API_ENDPOINT = '/v1.0/devices/factory-infos';
	private const USER_DEVICE_SPECIFICATIONS_API_ENDPOINT = '/v1.0/devices/%s/specifications';
	private const USER_DEVICE_STATUS_API_ENDPOINT = '/v1.0/devices/%s/status';

	private const DEVICES_API_ENDPOINT = '/v1.2/iot-03/devices';
	private const DEVICES_FACTORY_INFOS_API_ENDPOINT = '/v1.0/iot-03/devices/factory-infos';
	private const DEVICE_INFORMATION_API_ENDPOINT = '/v1.1/iot-03/devices/%s';
	private const DEVICE_SPECIFICATION_API_ENDPOINT = '/v1.2/iot-03/devices/%s/specification';
	private const DEVICE_STATUS_API_ENDPOINT = '/v1.0/iot-03/devices/%s/status';
	private const DEVICE_SEND_COMMAND_API_ENDPOINT = '/v1.0/iot-03/devices/%s/commands';

	public const ACCESS_TOKEN_MESSAGE_SCHEMA_FILENAME = 'openapi_access_token.json';
	public const REFRESH_TOKEN_MESSAGE_SCHEMA_FILENAME = 'openapi_refresh_token.json';

	public const USER_DEVICES_MESSAGE_SCHEMA_FILENAME = 'openapi_user_devices.json';
	public const USER_DEVICE_DETAIL_MESSAGE_SCHEMA_FILENAME = 'openapi_user_device_detail.json';
	public const USER_DEVICES_FACTORY_INFOS_MESSAGE_SCHEMA_FILENAME = 'openapi_user_devices_factory_infos.json';
	public const USER_DEVICE_SPECIFICATIONS_MESSAGE_SCHEMA_FILENAME = 'openapi_user_device_specifications.json';
	public const USER_DEVICE_STATUS_MESSAGE_SCHEMA_FILENAME = 'openapi_user_device_status.json';

	public const DEVICES_MESSAGE_SCHEMA_FILENAME = 'openapi_devices.json';
	public const DEVICES_FACTORY_INFOS_MESSAGE_SCHEMA_FILENAME = 'openapi_devices_factory_infos.json';
	public const DEVICE_INFORMATION_MESSAGE_SCHEMA_FILENAME = 'openapi_device_info.json';
	public const DEVICE_SPECIFICATION_MESSAGE_SCHEMA_FILENAME = 'openapi_device_specification.json';
	public const DEVICE_STATUS_MESSAGE_SCHEMA_FILENAME = 'openapi_device_status.json';
	public const DEVICE_SEND_COMMAND_MESSAGE_SCHEMA_FILENAME = 'openapi_device_send_command.json';

	/** @var Types\OpenApiEndpoint */
	private Types\OpenApiEndpoint $endpoint;

	/** @var string */
	private string $accessId;

	/** @var string */
	private string $accessSecret;

	/** @var string */
	private string $lang;

	/** @var string */
	private string $devChannel = 'fastybird_iot';

	/** @var Entities\API\TuyaTokenInfo|null */
	private ?Entities\API\TuyaTokenInfo $tokenInfo = null;

	/** @var EntityFactory */
	private EntityFactory $entityFactory;

	/** @var MetadataSchemas\IValidator */
	private MetadataSchemas\IValidator $schemaValidator;

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var GuzzleHttp\Client|null */
	private ?GuzzleHttp\Client $client = null;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param string $accessId
	 * @param string $accessSecret
	 * @param string $lang
	 * @param Types\OpenApiEndpoint $endpoint
	 * @param EntityFactory $entityFactory
	 * @param MetadataSchemas\IValidator $schemaValidator
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		string $accessId,
		string $accessSecret,
		string $lang,
		Types\OpenApiEndpoint $endpoint,
		EntityFactory $entityFactory,
		MetadataSchemas\IValidator $schemaValidator,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		?Log\LoggerInterface $logger = null
	) {
		$this->accessId = $accessId;
		$this->accessSecret = $accessSecret;
		$this->lang = $lang;
		$this->endpoint = $endpoint;

		$this->entityFactory = $entityFactory;
		$this->schemaValidator = $schemaValidator;
		$this->dateTimeFactory = $dateTimeFactory;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @return void
	 *
	 * @throws Throwable
	 */
	public function connect(): void
	{
		$response = $this->callRequest(
			'GET',
			self::ACCESS_TOKEN_API_ENDPOINT,
			[
				'grant_type' => 1,
			],
			null,
			false
		);

		if (!$response instanceof Message\ResponseInterface) {
			throw new Exceptions\InvalidState('Calling get access token returned invalid response');
		}

		$parsedMessage = $this->schemaValidator->validate(
			$response->getBody()->getContents(),
			$this->getSchemaFilePath(self::ACCESS_TOKEN_MESSAGE_SCHEMA_FILENAME)
		);

		$result = $parsedMessage->offsetGet('result');

		if (!$result instanceof Utils\ArrayHash) {
			throw new Exceptions\OpenApiCall('Received response is not valid');
		}

		$result->offsetSet(
			'expire_time',
			intval($parsedMessage->offsetGet('t')) + ($result->offsetExists('expire') ? $result->offsetGet('expire') : $result->offsetGet('expire_time')) * 1000
		);

		$this->tokenInfo = $this->entityFactory->build(
			Entities\API\TuyaTokenInfo::class,
			$result
		);
	}

	/**
	 * @return void
	 */
	public function disconnect(): void
	{
		$this->client = null;
		$this->tokenInfo = null;
	}

	/**
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return $this->tokenInfo !== null;
	}

	/**
	 * @return string
	 *
	 * @throws Throwable
	 */
	public function getUid(): string
	{
		if (!$this->isConnected()) {
			$this->connect();
		}

		if ($this->tokenInfo === null) {
			throw new Exceptions\OpenApiCall('Access token could not be created');
		}

		return $this->tokenInfo->getUid();
	}

	/**
	 * @param string $userId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDevices(
		string $userId
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			sprintf(self::USER_DEVICES_API_ENDPOINT, $userId)
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::USER_DEVICES_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$devices = [];

					foreach ($result as $deviceData) {
						if (!$deviceData instanceof Utils\ArrayHash) {
							continue;
						}

						$devices[] = $this->entityFactory->build(
							Entities\API\UserDeviceDetail::class,
							$deviceData
						);
					}

					$promise->resolve($devices);
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string[] $deviceIds
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDevicesFactoryInfos(
		array $deviceIds
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			self::USER_DEVICES_FACTORY_INFOS_API_ENDPOINT,
			[
				'device_ids' => implode(',', $deviceIds),
			]
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::USER_DEVICES_FACTORY_INFOS_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$factoryInfos = [];

					foreach ($result as $deviceData) {
						if (!$deviceData instanceof Utils\ArrayHash) {
							continue;
						}

						$factoryInfos[] = $this->entityFactory->build(
							Entities\API\UserDeviceFactoryInfos::class,
							$deviceData
						);
					}

					$promise->resolve($factoryInfos);
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDeviceDetail(
		string $deviceId
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			sprintf(self::USER_DEVICE_DETAIL_API_ENDPOINT, $deviceId)
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::USER_DEVICE_DETAIL_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$deviceStatus = [];

					if (
						$result->offsetExists('status')
						&& (
							is_array($result->offsetGet('status'))
							|| $result->offsetGet('status') instanceof Utils\ArrayHash
						)
					) {
						foreach ($result->offsetGet('status') as $item) {
							$deviceStatus[] = $this->entityFactory->build(
								Entities\API\UserDeviceDataPointStatus::class,
								$item
							);
						}
					}

					$result->offsetSet('status', $deviceStatus);

					$promise->resolve($this->entityFactory->build(
						Entities\API\UserDeviceDetail::class,
						$result
					));
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDeviceSpecifications(
		string $deviceId
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			sprintf(self::USER_DEVICE_SPECIFICATIONS_API_ENDPOINT, $deviceId)
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::USER_DEVICE_SPECIFICATIONS_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$deviceFunctions = [];

					if (
						$result->offsetExists('functions')
						&& (
							is_array($result->offsetGet('functions'))
							|| $result->offsetGet('functions') instanceof Utils\ArrayHash
						)
					) {
						foreach ($result->offsetGet('functions') as $item) {
							$deviceFunctions[] = $this->entityFactory->build(
								Entities\API\UserDeviceSpecificationsFunction::class,
								$item
							);
						}
					}

					$result->offsetSet('functions', $deviceFunctions);

					$deviceStatus = [];

					if (
						$result->offsetExists('status')
						&& (
							is_array($result->offsetGet('status'))
							|| $result->offsetGet('status') instanceof Utils\ArrayHash
						)
					) {
						foreach ($result->offsetGet('status') as $item) {
							$deviceStatus[] = $this->entityFactory->build(
								Entities\API\UserDeviceSpecificationsStatus::class,
								$item
							);
						}
					}

					$result->offsetSet('status', $deviceStatus);

					$promise->resolve($this->entityFactory->build(
						Entities\API\UserDeviceSpecifications::class,
						$result
					));
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDeviceStatus(
		string $deviceId
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			sprintf(self::USER_DEVICE_STATUS_API_ENDPOINT, $deviceId)
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::USER_DEVICE_STATUS_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$statuses = [];

					foreach ($result as $statusData) {
						if (!$statusData instanceof Utils\ArrayHash) {
							continue;
						}

						$statuses[] = $this->entityFactory->build(
							Entities\API\UserDeviceDataPointStatus::class,
							$statusData
						);
					}

					$promise->resolve($statuses);
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param Array<string, mixed> $params
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getDevices(
		array $params = []
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			self::DEVICES_API_ENDPOINT,
			$params
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::DEVICES_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$list = $result->offsetGet('list');

					if (!$list instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$devices = [];

					foreach ($list as $deviceData) {
						if (!$deviceData instanceof Utils\ArrayHash) {
							continue;
						}

						$devices[] = $this->entityFactory->build(
							Entities\API\DeviceInformation::class,
							$deviceData
						);
					}

					$promise->resolve($devices);
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string[] $deviceIds
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getDevicesFactoryInfos(
		array $deviceIds
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			self::DEVICES_FACTORY_INFOS_API_ENDPOINT,
			[
				'device_ids' => implode(',', $deviceIds),
			]
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::DEVICES_FACTORY_INFOS_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$factoryInfos = [];

					foreach ($result as $deviceData) {
						if (!$deviceData instanceof Utils\ArrayHash) {
							continue;
						}

						$factoryInfos[] = $this->entityFactory->build(
							Entities\API\DeviceFactoryInfos::class,
							$deviceData
						);
					}

					$promise->resolve($factoryInfos);
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getDeviceInformation(
		string $deviceId
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			sprintf(self::DEVICE_INFORMATION_API_ENDPOINT, $deviceId)
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::DEVICE_INFORMATION_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$promise->resolve($this->entityFactory->build(
						Entities\API\DeviceInformation::class,
						$result
					));
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getDeviceSpecification(
		string $deviceId
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			sprintf(self::DEVICE_SPECIFICATION_API_ENDPOINT, $deviceId)
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::DEVICE_SPECIFICATION_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$deviceFunctions = [];

					if (
						$result->offsetExists('functions')
						&& (
							is_array($result->offsetGet('functions'))
							|| $result->offsetGet('functions') instanceof Utils\ArrayHash
						)
					) {
						foreach ($result->offsetGet('functions') as $item) {
							$deviceFunctions[] = $this->entityFactory->build(
								Entities\API\DeviceSpecificationFunction::class,
								$item
							);
						}
					}

					$result->offsetSet('functions', $deviceFunctions);

					$deviceStatus = [];

					if (
						$result->offsetExists('status')
						&& (
							is_array($result->offsetGet('status'))
							|| $result->offsetGet('status') instanceof Utils\ArrayHash
						)
					) {
						foreach ($result->offsetGet('status') as $item) {
							$deviceStatus[] = $this->entityFactory->build(
								Entities\API\DeviceSpecificationStatus::class,
								$item
							);
						}
					}

					$result->offsetSet('status', $deviceStatus);

					$promise->resolve($this->entityFactory->build(
						Entities\API\DeviceSpecification::class,
						$result
					));
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getDeviceStatus(
		string $deviceId
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		$result = $this->callRequest(
			'GET',
			sprintf(self::DEVICE_STATUS_API_ENDPOINT, $deviceId)
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::DEVICE_STATUS_MESSAGE_SCHEMA_FILENAME)
					);

					$result = $parsedMessage->offsetGet('result');

					if (!$result instanceof Utils\ArrayHash) {
						throw new Exceptions\OpenApiCall('Received response is not valid');
					}

					$statuses = [];

					foreach ($result as $statusData) {
						if (!$statusData instanceof Utils\ArrayHash) {
							continue;
						}

						$statuses[] = $this->entityFactory->build(
							Entities\API\DeviceDataPointStatus::class,
							$statusData
						);
					}

					$promise->resolve($statuses);
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 * @param string $code
	 * @param string|int|float|bool $value
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function setDeviceStatus(
		string $deviceId,
		string $code,
		string|int|float|bool $value
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			$this->connect();
		}

		$promise = new Promise\Deferred();

		try {
			$body = Utils\Json::encode([
				'commands' => [
					[
						'code'  => $code,
						'value' => $value,
					],
				],
			]);
		} catch (Utils\JsonException $ex) {
			return Promise\reject(new Exceptions\OpenApiCall(
				'Message body could not be encoded',
				$ex->getCode(),
				$ex
			));
		}

		$result = $this->callRequest(
			'POST',
			sprintf(self::DEVICE_SEND_COMMAND_API_ENDPOINT, $deviceId),
			[],
			$body
		);

		if ($result instanceof Promise\PromiseInterface) {
			$result
				->then(function (Message\ResponseInterface $response) use ($promise): void {
					$parsedMessage = $this->schemaValidator->validate(
						$response->getBody()->getContents(),
						$this->getSchemaFilePath(self::DEVICE_SEND_COMMAND_MESSAGE_SCHEMA_FILENAME)
					);

					$promise->resolve(boolval($parsedMessage->offsetGet('result')));
				})
				->otherwise(function (Throwable $ex) use ($promise): void {
					$promise->reject($ex);
				});
		} else {
			throw new Exceptions\InvalidState('Request promise could not be created');
		}

		return $promise->promise();
	}

	/**
	 * @param string $method
	 * @param string $path
	 * @param Array<string, mixed> $params
	 * @param string|null $body
	 * @param bool $async
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface|Message\ResponseInterface|false
	 *
	 * @throws Throwable
	 */
	private function callRequest(
		string $method,
		string $path,
		array $params = [],
		?string $body = null,
		bool $async = true
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface|Message\ResponseInterface|false {
		$this->refreshAccessToken($path);

		$deferred = new Promise\Deferred();

		$this->logger->debug(sprintf(
			'Request: method = %s url = %s',
			$method,
			($this->endpoint->getValue() . $path)
		), [
			'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
			'type'    => 'openapi-api',
			'request' => [
				'method' => $method,
				'url'    => ($this->endpoint->getValue() . $path),
				'params' => $params,
				'body'   => $body,
			],
		]);

		$requestPath = $this->endpoint->getValue() . $path;

		if (count($params) > 0) {
			$requestPath .= '?';
			$requestPath .= http_build_query($params);
		}

		if ($async) {
			$this->getClient()->requestAsync(
				$method,
				$requestPath,
				[
					'headers' => $this->buildRequestHeaders($method, $path, $params, $body),
					'body'    => $body ?? '',
				]
			)
				->then(function (Message\ResponseInterface $response) use ($deferred, $method, $path, $params, $body): void {
					try {
						$response = $this->checkResponse($path, $response);

					} catch (Exceptions\OpenApiCall $ex) {
						$this->logger->error('Received payload is not valid', [
							'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
							'type'    => 'openapi-api',
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
							'request' => [
								'method' => $method,
								'url'    => ($this->endpoint->getValue() . $path),
								'params' => $params,
								'body'   => $body,
							],
						]);

						$deferred->reject($ex);

						return;
					}

					$deferred->resolve($response);
				})
				->otherwise(function (Throwable $ex) use ($deferred, $method, $path, $params, $body): void {
					$this->logger->error('Calling api endpoint failed', [
						'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
						'type'    => 'openapi-api',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
						'request' => [
							'method' => $method,
							'url'    => ($this->endpoint->getValue() . $path),
							'params' => $params,
							'body'   => $body,
						],
					]);

					$deferred->reject($ex);
				});

			return $deferred->promise();
		} else {
			try {
				$response = $this->getClient()->request(
					$method,
					$requestPath,
					[
						'headers' => $this->buildRequestHeaders($method, $path, $params, $body),
						'body'    => $body ?? '',
					]
				);

				$response = $this->checkResponse($path, $response);

			} catch (GuzzleHttp\Exception\GuzzleException $ex) {
				$this->logger->error('Calling api endpoint failed', [
					'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'    => 'openapi-api',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'request' => [
						'method' => $method,
						'url'    => ($this->endpoint->getValue() . $path),
						'params' => $params,
						'body'   => $body,
					],
				]);

				return false;

			} catch (Exceptions\OpenApiCall $ex) {
				$this->logger->error('Received payload is not valid', [
					'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'    => 'openapi-api',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'request' => [
						'method' => $method,
						'url'    => ($this->endpoint->getValue() . $path),
						'params' => $params,
						'body'   => $body,
					],
				]);

				return false;
			}

			return $response;
		}
	}

	/**
	 * @param string $path
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 *
	 * @throws Throwable
	 */
	private function checkResponse(string $path, Message\ResponseInterface $response): Message\ResponseInterface
	{
		$body = $response->getBody()->getContents();

		try {
			$decodedResponse = Utils\Json::decode($body, Utils\Json::FORCE_ARRAY);

		} catch (Utils\JsonException) {
			throw new Exceptions\OpenApiCall('Received response body is not valid JSON');
		}

		if (!is_array($decodedResponse)) {
			throw new Exceptions\OpenApiCall('Received response body is not valid JSON');
		}

		$data = Utils\ArrayHash::from($decodedResponse);

		if (
			$data->offsetExists('code')
			&& $data->offsetGet('code') === self::TUYA_ERROR_CODE_TOKEN_INVALID
		) {
			$this->tokenInfo = null;

			if ($path !== self::ACCESS_TOKEN_API_ENDPOINT) {
				$this->connect();

			} else {
				throw new Exceptions\OpenApiCall('API token is not valid and can not be refreshed');
			}
		}

		if (
			$data->offsetExists('success')
			&& boolval($data->offsetGet('success')) !== true
		) {
			if ($data->offsetExists('msg')) {
				throw new Exceptions\OpenApiCall(strval($data->offsetGet('msg')));
			}

			throw new Exceptions\OpenApiCall('Received response is not success');
		}

		$response->getBody()->rewind();

		return $response;
	}

	/**
	 * @param string $path
	 *
	 * @return void
	 */
	private function refreshAccessToken(string $path): void
	{
		if (Utils\Strings::startsWith($path, self::ACCESS_TOKEN_API_ENDPOINT)) {
			return;
		}

		if ($this->tokenInfo === null) {
			return;
		}

		$tokenExpireTime = $this->tokenInfo->getExpireTime();

		if (($tokenExpireTime - 60 * 1000) > intval($this->dateTimeFactory->getNow()->format('Uv'))) { // 1min
			return;
		}

		try {
			$response = $this->getClient()->get(
				$this->endpoint->getValue() . sprintf(
					self::REFRESH_TOKEN_API_ENDPOINT,
					$this->tokenInfo->getRefreshToken()
				),
				$this->buildRequestHeaders('get', self::REFRESH_TOKEN_API_ENDPOINT),
			);

			$parsedMessage = $this->schemaValidator->validate(
				$response->getBody()->getContents(),
				$this->getSchemaFilePath(self::REFRESH_TOKEN_MESSAGE_SCHEMA_FILENAME)
			);

			$result = $parsedMessage->offsetGet('result');

			if (!$result instanceof Utils\ArrayHash) {
				throw new Exceptions\OpenApiCall('Received response is not valid');
			}

			$result->offsetSet(
				'expire_time',
				intval($parsedMessage->offsetGet('t')) + ($result->offsetExists('expire') ? $result->offsetGet('expire') : $result->offsetGet('expire_time')) * 1000
			);

			$this->tokenInfo = $this->entityFactory->build(
				Entities\API\TuyaTokenInfo::class,
				$result
			);
		} catch (GuzzleHttp\Exception\GuzzleException $ex) {
			$this->logger->error(
				'Could not refresh access token',
				[
					'source'    => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'    => 'openapi-api',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]
			);
		}
	}

	/**
	 * @param string $method
	 * @param string $path
	 * @param Array<string, mixed> $params
	 * @param string|null $body
	 *
	 * @return Array<string, string|int>
	 */
	private function buildRequestHeaders(
		string $method,
		string $path,
		array $params = [],
		?string $body = null
	): array {
		$accessToken = $this->tokenInfo?->getAccessToken();

		$sign = $this->calculateSign($method, $path, $params, $body);

		return [
			'client_id'    => $this->accessId,
			'sign'         => $sign->getSign(),
			'sign_method'  => 'HMAC-SHA256',
			'access_token' => $accessToken ?: '',
			't'            => $sign->getTimestamp(),
			'lang'         => $this->lang,
			'dev_lang'     => 'php',
			'dev_version'  => self::VERSION,
			'dev_channel'  => 'cloud_' . $this->devChannel,
		];
	}

	/**
	 * @param string $method
	 * @param string $path
	 * @param Array<string, mixed> $params
	 * @param string|null $body
	 *
	 * @return Entities\API\Sign
	 */
	private function calculateSign(
		string $method,
		string $path,
		array $params = [],
		?string $body = null
	): Entities\API\Sign {
		$strToSign = $method;
		$strToSign .= "\n";

		// Content-SHA256
		$contentToSha256 = $body === null || $body === '' ? '' : $body;

		$strToSign .= hash('sha256', $contentToSha256);
		$strToSign .= "\n";

		// Header
		$strToSign .= "\n";

		// URL
		$strToSign .= $path;

		if (count($params) > 0) {
			$strToSign .= '?';
			$strToSign .= urldecode(http_build_query($params));
		}

		// Sign
		$timestamp = intval($this->dateTimeFactory->getNow()->format('Uv'));

		$message = $this->accessId;

		if ($this->tokenInfo !== null) {
			$message .= $this->tokenInfo->getAccessToken();
		}

		$message .= $timestamp . $strToSign;

		$sign = Utils\Strings::upper(hash_hmac('sha256', $message, $this->accessSecret));

		return new Entities\API\Sign($sign, $timestamp);
	}

	/**
	 * @return GuzzleHttp\Client
	 */
	private function getClient(): GuzzleHttp\Client
	{
		if ($this->client === null) {
			$this->client = new GuzzleHttp\Client();
		}

		return $this->client;
	}

	/**
	 * @param string $schemaFilename
	 *
	 * @return string
	 */
	private function getSchemaFilePath(string $schemaFilename): string
	{
		try {
			$schema = Utils\FileSystem::read(TuyaConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . $schemaFilename);

		} catch (Nette\IOException) {
			throw new Exceptions\OpenApiCall('Validation schema for response could not be loaded');
		}

		return $schema;
	}

}
