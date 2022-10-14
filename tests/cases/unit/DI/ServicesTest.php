<?php declare(strict_types = 1);

namespace Tests\Cases\Unit\DI;

use FastyBird\TuyaConnector\Hydrators;
use FastyBird\TuyaConnector\Schemas;
use Nette;
use Tests\Cases\Unit\BaseTestCase;

final class ServicesTest extends BaseTestCase
{

	/**
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		self::assertNotNull($container->getByType(Hydrators\TuyaConnector::class, false));
		self::assertNotNull($container->getByType(Hydrators\TuyaDevice::class, false));

		self::assertNotNull($container->getByType(Schemas\TuyaConnector::class, false));
		self::assertNotNull($container->getByType(Schemas\TuyaDevice::class, false));
	}

}
