<?php declare(strict_types = 1);

/**
 * ConnectorFactory.php
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
use Nette;
use Nette\Utils;
use Psr\Http\Message;
use Psr\Log;
use React\Async;
use React\EventLoop;
use React\Http;
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
final class OpenApiApi
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

	private const DEVICE_INFORMATION_API_ENDPOINT = '/v1.1/iot-03/devices/%s';
	private const DEVICE_SEND_COMMAND_API_ENDPOINT = '/v1.0/iot-03/devices/%s/commands';

	public const ACCESS_TOKEN_MESSAGE_SCHEMA_FILENAME = 'openapi_access_token.json';
	public const REFRESH_TOKEN_MESSAGE_SCHEMA_FILENAME = 'openapi_refresh_token.json';

	public const USER_DEVICES_MESSAGE_SCHEMA_FILENAME = 'openapi_user_devices.json';
	public const USER_DEVICE_DETAIL_MESSAGE_SCHEMA_FILENAME = 'openapi_user_device_detail.json';
	public const USER_DEVICES_FACTORY_INFO_MESSAGE_SCHEMA_FILENAME = 'openapi_user_devices_factory_info.json';
	public const USER_DEVICE_SPECIFICATIONS_MESSAGE_SCHEMA_FILENAME = 'openapi_user_device_specifications.json';
	public const USER_DEVICE_STATUS_MESSAGE_SCHEMA_FILENAME = 'openapi_user_device_status.json';

	public const DEVICE_INFORMATION_MESSAGE_SCHEMA_FILENAME = 'openapi_device_info.json';
	public const DEVICE_SEND_COMMAND_MESSAGE_SCHEMA_FILENAME = 'openapi_device_send_command.json';

	/** @var Types\OpenApiEndpointType */
	private Types\OpenApiEndpointType $endpoint;

	/** @var string */
	private string $accessId;

	/** @var string */
	private string $accessSecret;

	/** @var string */
	private string $lang;

	/** @var string */
	private string $devChannel = 'fastybird_iot';

	/** @var Entities\API\TuyaTokenInfoEntity|null */
	private ?Entities\API\TuyaTokenInfoEntity $tokenInfo = null;

	/** @var OpenApiEntityFactory */
	private OpenApiEntityFactory $entityFactory;

	/** @var MetadataSchemas\IValidator */
	private MetadataSchemas\IValidator $schemaValidator;

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var Http\Browser|null */
	private ?Http\Browser $browser = null;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param string $accessId
	 * @param string $accessSecret
	 * @param string $lang
	 * @param Types\OpenApiEndpointType $endpoint
	 * @param OpenApiEntityFactory $entityFactory
	 * @param MetadataSchemas\IValidator $schemaValidator
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		string $accessId,
		string $accessSecret,
		string $lang,
		Types\OpenApiEndpointType $endpoint,
		OpenApiEntityFactory $entityFactory,
		MetadataSchemas\IValidator $schemaValidator,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->accessId = $accessId;
		$this->accessSecret = $accessSecret;
		$this->lang = $lang;
		$this->endpoint = $endpoint;

		$this->entityFactory = $entityFactory;
		$this->schemaValidator = $schemaValidator;
		$this->dateTimeFactory = $dateTimeFactory;
		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function connect(): Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	{
		return $this->callRequest(
			'GET',
			self::ACCESS_TOKEN_API_ENDPOINT,
			[
				'grant_type' => 1,
			]
		)
			->then(function (Message\ResponseInterface $response): void {
				$parsedMessage = $this->schemaValidator->validate(
					$response->getBody()->getContents(),
					$this->getSchemaFilePath(self::ACCESS_TOKEN_MESSAGE_SCHEMA_FILENAME)
				);

				$result = $parsedMessage->offsetGet('result');

				if (!$result instanceof Utils\ArrayHash) {
					throw new Exceptions\OpenApiCallException('Received response is not valid');
				}

				$result->offsetSet(
					'expire_time',
					intval($parsedMessage->offsetGet('t')) + ($result->offsetExists('expire') ? $result->offsetGet('expire') : $result->offsetGet('expire_time')) * 1000
				);

				$this->tokenInfo = $this->entityFactory->build(
					Entities\API\TuyaTokenInfoEntity::class,
					$result
				);
			});
	}

	/**
	 * @return void
	 */
	public function disconnect(): void
	{
		$this->browser = null;
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
	 * @param string $userId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDevices(string $userId): Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	{
		if (!$this->isConnected()) {
			Async\await($this->connect());
		}

		$promise = new Promise\Deferred();

		$this->callRequest(
			'GET',
			sprintf(self::USER_DEVICES_API_ENDPOINT, $userId)
		)
			->then(function (Message\ResponseInterface $response) use ($promise): void {
				$parsedMessage = $this->schemaValidator->validate(
					$response->getBody()->getContents(),
					$this->getSchemaFilePath(self::USER_DEVICES_MESSAGE_SCHEMA_FILENAME)
				);

				$result = $parsedMessage->offsetGet('result');

				if (!$result instanceof Utils\ArrayHash) {
					throw new Exceptions\OpenApiCallException('Received response is not valid');
				}

				$devices = [];

				foreach ($result as $deviceData) {
					if (!$deviceData instanceof Utils\ArrayHash) {
						continue;
					}

					$devices[] = $this->entityFactory->build(
						Entities\API\UserDeviceDetailEntity::class,
						$deviceData
					);
				}

				$promise->resolve($devices);
			})
			->otherwise(function (Throwable $ex) use ($promise): void {
				$promise->reject($ex);
			});

		return $promise->promise();
	}

	/**
	 * @param string[] $deviceIds
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDevicesFactoryInfos(array $deviceIds): Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	{
		if (!$this->isConnected()) {
			Async\await($this->connect());
		}

		$promise = new Promise\Deferred();

		$this->callRequest(
			'GET',
			self::USER_DEVICES_FACTORY_INFOS_API_ENDPOINT,
			[
				'device_ids' => $deviceIds
			]
		)
			->then(function (Message\ResponseInterface $response) use ($promise): void {
				$parsedMessage = $this->schemaValidator->validate(
					$response->getBody()->getContents(),
					$this->getSchemaFilePath(self::USER_DEVICES_FACTORY_INFO_MESSAGE_SCHEMA_FILENAME)
				);

				$result = $parsedMessage->offsetGet('result');

				if (!$result instanceof Utils\ArrayHash) {
					throw new Exceptions\OpenApiCallException('Received response is not valid');
				}

				$factoryInfos = [];

				foreach ($result as $deviceData) {
					if (!$deviceData instanceof Utils\ArrayHash) {
						continue;
					}

					$factoryInfos[] = $this->entityFactory->build(
						Entities\API\UserDeviceFactoryInfoEntity::class,
						$deviceData
					);
				}

				$promise->resolve($factoryInfos);
			})
			->otherwise(function (Throwable $ex) use ($promise): void {
				$promise->reject($ex);
			});

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDeviceDetail(string $deviceId): Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	{
		if (!$this->isConnected()) {
			Async\await($this->connect());
		}

		$promise = new Promise\Deferred();

		$this->callRequest(
			'GET',
			sprintf(self::USER_DEVICE_DETAIL_API_ENDPOINT, $deviceId)
		)
			->then(function (Message\ResponseInterface $response) use ($promise): void {
				$parsedMessage = $this->schemaValidator->validate(
					$response->getBody()->getContents(),
					$this->getSchemaFilePath(self::USER_DEVICE_DETAIL_MESSAGE_SCHEMA_FILENAME)
				);

				$result = $parsedMessage->offsetGet('result');

				if (!$result instanceof Utils\ArrayHash) {
					throw new Exceptions\OpenApiCallException('Received response is not valid');
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
							Entities\API\UserDeviceStatusEntity::class,
							$item
						);
					}
				}

				$result->offsetSet('status', $deviceStatus);

				$promise->resolve($this->entityFactory->build(
					Entities\API\UserDeviceDetailEntity::class,
					$result
				));
			})
			->otherwise(function (Throwable $ex) use ($promise): void {
				$promise->reject($ex);
			});

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDeviceSpecifications(string $deviceId): Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	{
		if (!$this->isConnected()) {
			Async\await($this->connect());
		}

		$promise = new Promise\Deferred();

		$this->callRequest(
			'GET',
			sprintf(self::USER_DEVICE_SPECIFICATIONS_API_ENDPOINT, $deviceId)
		)
			->then(function (Message\ResponseInterface $response) use ($promise): void {
				$parsedMessage = $this->schemaValidator->validate(
					$response->getBody()->getContents(),
					$this->getSchemaFilePath(self::USER_DEVICE_SPECIFICATIONS_MESSAGE_SCHEMA_FILENAME)
				);

				$result = $parsedMessage->offsetGet('result');

				if (!$result instanceof Utils\ArrayHash) {
					throw new Exceptions\OpenApiCallException('Received response is not valid');
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
							Entities\API\UserDeviceSpecificationsFunctionEntity::class,
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
							Entities\API\UserDeviceSpecificationsStatusEntity::class,
							$item
						);
					}
				}

				$result->offsetSet('status', $deviceStatus);

				$promise->resolve($this->entityFactory->build(
					Entities\API\UserDeviceSpecificationsEntity::class,
					$result
				));
			})
			->otherwise(function (Throwable $ex) use ($promise): void {
				$promise->reject($ex);
			});

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getUserDeviceStatus(string $deviceId): Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	{
		if (!$this->isConnected()) {
			Async\await($this->connect());
		}

		$promise = new Promise\Deferred();

		$this->callRequest(
			'GET',
			sprintf(self::USER_DEVICE_STATUS_API_ENDPOINT, $deviceId)
		)
			->then(function (Message\ResponseInterface $response) use ($promise): void {
				$parsedMessage = $this->schemaValidator->validate(
					$response->getBody()->getContents(),
					$this->getSchemaFilePath(self::USER_DEVICE_STATUS_MESSAGE_SCHEMA_FILENAME)
				);

				$result = $parsedMessage->offsetGet('result');

				if (!$result instanceof Utils\ArrayHash) {
					throw new Exceptions\OpenApiCallException('Received response is not valid');
				}

				$statuses = [];

				foreach ($result as $statusData) {
					if (!$statusData instanceof Utils\ArrayHash) {
						continue;
					}

					$statuses[] = $this->entityFactory->build(
						Entities\API\UserDeviceStatusEntity::class,
						$statusData
					);
				}

				$promise->resolve($statuses);
			})
			->otherwise(function (Throwable $ex) use ($promise): void {
				$promise->reject($ex);
			});

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function getDeviceInformation(string $deviceId): Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	{
		if (!$this->isConnected()) {
			Async\await($this->connect());
		}

		$promise = new Promise\Deferred();

		$this->callRequest(
			'GET',
			sprintf(self::DEVICE_INFORMATION_API_ENDPOINT, $deviceId)
		)
			->then(function (Message\ResponseInterface $response) use ($promise): void {
				$parsedMessage = $this->schemaValidator->validate(
					$response->getBody()->getContents(),
					$this->getSchemaFilePath(self::DEVICE_INFORMATION_MESSAGE_SCHEMA_FILENAME)
				);

				$result = $parsedMessage->offsetGet('result');

				if (!$result instanceof Utils\ArrayHash) {
					throw new Exceptions\OpenApiCallException('Received response is not valid');
				}

				$promise->resolve($this->entityFactory->build(
					Entities\API\DeviceInformationEntity::class,
					$result
				));
			})
			->otherwise(function (Throwable $ex) use ($promise): void {
				$promise->reject($ex);
			});

		return $promise->promise();
	}

	/**
	 * @param string $deviceId
	 * @param string $code
	 * @param string|int|bool $value
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	public function sendCommand(
		string $deviceId,
		string $code,
		string|int|bool $value
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		if (!$this->isConnected()) {
			Async\await($this->connect());
		}

		$promise = new Promise\Deferred();

		$body = json_encode([
			'commands' => [
				[
					'code'  => $code,
					'value' => $value,
				],
			],
		]);

		if ($body === false) {
			return Promise\reject(new \Exception('Message body could not be encoded'));
		}

		$this->callRequest(
			'POST',
			sprintf(self::DEVICE_SEND_COMMAND_API_ENDPOINT, $deviceId),
			[],
			$body
		)
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

		return $promise->promise();
	}

	/**
	 * @param string $method
	 * @param string $path
	 * @param Array<string, mixed> $params
	 * @param string|null $body
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws Throwable
	 */
	private function callRequest(
		string $method,
		string $path,
		array $params = [],
		?string $body = null
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		Async\await($this->refreshAccessToken($path));

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

		$this->getBrowser()
			->request(
				$method,
				$requestPath,
				$this->buildRequestHeaders($method, $path, $params, $body),
				$body === null ? '' : $body
			)
			->then(function (Message\ResponseInterface $response) use ($deferred, $path): void {
				try {
					$decodedResponse = Utils\Json::decode($response->getBody()->getContents(), Utils\Json::FORCE_ARRAY);

				} catch(Utils\JsonException) {
					$deferred->reject(new Exceptions\OpenApiCallException('Received response body is not valid JSON'));

					return;
				}

				if (!is_array($decodedResponse)) {
					$deferred->reject(new Exceptions\OpenApiCallException('Received response body is not valid JSON'));

					return;
				}

				$data = Utils\ArrayHash::from($decodedResponse);

				if (
					$data->offsetExists('code')
					&& $data->offsetGet('code') === self::TUYA_ERROR_CODE_TOKEN_INVALID
				) {
					$this->tokenInfo = null;

					if ($path !== self::ACCESS_TOKEN_API_ENDPOINT) {
						Async\await($this->connect());

					} else {
						$deferred->reject(new Exceptions\OpenApiCallException('API token is not valid and can not be refreshed'));

						return;
					}
				}

				if (
					$data->offsetExists('success')
					&& boolval($data->offsetGet('success')) !== true
				) {
					if ($data->offsetExists('msg')) {
						$deferred->reject(new Exceptions\OpenApiCallException(strval($data->offsetGet('msg'))));
					} else {
						$deferred->reject(new Exceptions\OpenApiCallException('Received response is not success'));
					}

					return;
				}

				$response->getBody()->rewind();

				$deferred->resolve($response);
			})
			->otherwise(function (Throwable $ex) use ($deferred, $method, $path, $params, $body): void {
				$this->logger->error('Calling api endpoint failed', [
					'source'  => Metadata\Constants::CONNECTOR_TUYA_SOURCE,
					'type'    => 'openapi-api',
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
	}

	/**
	 * @param string $path
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 */
	private function refreshAccessToken(string $path): Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	{
		$deferred = new Promise\Deferred();

		if (Utils\Strings::startsWith($path, self::ACCESS_TOKEN_API_ENDPOINT)) {
			return Promise\resolve();
		}

		if ($this->tokenInfo === null) {
			return Promise\resolve();
		}

		$tokenExpireTime = $this->tokenInfo->getExpireTime();

		if (($tokenExpireTime - 60 * 1000) > intval($this->dateTimeFactory->getNow()->format('Uv'))) { // 1min
			return Promise\resolve();
		}

		$this->getBrowser()->get(
			$this->endpoint->getValue() . sprintf(
				self::REFRESH_TOKEN_API_ENDPOINT,
				$this->tokenInfo->getRefreshToken()
			),
			$this->buildRequestHeaders('get', self::REFRESH_TOKEN_API_ENDPOINT),
		)
			->then(function (Message\ResponseInterface $response) use ($deferred): void {
				$parsedMessage = $this->schemaValidator->validate(
					$response->getBody()->getContents(),
					$this->getSchemaFilePath(self::REFRESH_TOKEN_MESSAGE_SCHEMA_FILENAME)
				);

				$result = $parsedMessage->offsetGet('result');

				if (!$result instanceof Utils\ArrayHash) {
					throw new Exceptions\OpenApiCallException('Received response is not valid');
				}

				$result->offsetSet(
					'expire_time',
					intval($parsedMessage->offsetGet('t')) + ($result->offsetExists('expire') ? $result->offsetGet('expire') : $result->offsetGet('expire_time')) * 1000
				);

				$this->tokenInfo = $this->entityFactory->build(
					Entities\API\TuyaTokenInfoEntity::class,
					$result
				);

				$deferred->resolve();
			})
			->otherwise(function (Throwable $ex) use ($deferred): void {
				$deferred->reject($ex);
			});

		return $deferred->promise();
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
	 * @return Entities\API\SignEntity
	 */
	private function calculateSign(
		string $method,
		string $path,
		array $params = [],
		?string $body = null
	): Entities\API\SignEntity {
		$strToSign = $method;
		$strToSign .= "\n";

		// Content-SHA256
		$contentToSha256 = $body === null || $body === '' ? '' : $body;

		$strToSign .= hash('sha256', $contentToSha256);
		$strToSign .= "\n";

		// Header
		$strToSign .= "\n";

		# URL
		$strToSign .= $path;

		if (count($params) > 0) {
			$strToSign .= '?';
			$strToSign .= http_build_query($params);
		}

		// Sign
		$timestamp = intval($this->dateTimeFactory->getNow()->format('Uv'));

		$message = $this->accessId;

		if ($this->tokenInfo !== null) {
			$message .= $this->tokenInfo->getAccessToken();
		}

		$message .= $timestamp . $strToSign;

		$sign = Utils\Strings::upper(hash_hmac('sha256', $message, $this->accessSecret));

		return new Entities\API\SignEntity($sign, $timestamp);
	}

	/**
	 * @return Http\Browser
	 */
	private function getBrowser(): Http\Browser
	{
		if ($this->browser === null) {
			$this->browser = new Http\Browser($this->eventLoop);
		}

		return $this->browser;
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
			throw new Exceptions\OpenApiCallException('Validation schema for response could not be loaded');
		}

		return $schema;
	}

}
