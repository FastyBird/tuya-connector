<?php declare(strict_types = 1);

namespace Tests\Cases;

use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../BaseTestCase.php';

/**
 * @testCase
 */
final class ServicesTest extends BaseTestCase
{

	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		Assert::true(true);
	}

}

$test_case = new ServicesTest();
$test_case->run();
