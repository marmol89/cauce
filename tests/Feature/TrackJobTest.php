<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Marmol89\Cauce\Contracts\RetryStrategy;
use Marmol89\Cauce\Middleware\TrackJob;
use Marmol89\Cauce\Retry\RetryManager;
use Marmol89\Cauce\Tests\TestCase;

class TrackJobTest extends TestCase
{
	public function test_can_be_instantiated(): void
	{
		$manager = $this->createStub(RetryManager::class);
		$middleware = new TrackJob($manager);

		$this->assertInstanceOf(TrackJob::class, $middleware);
	}

	public function test_tags_the_job_payload_with_retry_strategy_info(): void
	{
		$strategy = $this->createStub(RetryStrategy::class);
		$strategy->method('name')->willReturn('linear');
		$strategy->method('maxAttempts')->willReturn(5);

		$manager = $this->createMock(RetryManager::class);
		$manager->expects($this->once())
			->method('forJob')
			->willReturn($strategy);

		$job = new class {
			public bool $getPayloadCalled = false;

			public function getPayload(): array
			{
				$this->getPayloadCalled = true;

				return [];
			}
		};

		$nextCalled = false;
		$next = function ($job) use (&$nextCalled) {
			$nextCalled = true;

			return $job;
		};

		$middleware = new TrackJob($manager);
		$result = $middleware->handle($job, $next);

		$this->assertTrue($job->getPayloadCalled);
		$this->assertTrue($nextCalled);
		$this->assertSame($job, $result);
	}

	public function test_skips_tagging_when_strategy_is_null(): void
	{
		$manager = $this->createMock(RetryManager::class);
		$manager->expects($this->once())
			->method('forJob')
			->willReturn(null);

		$job = new class {
			public bool $getPayloadCalled = false;

			public function getPayload(): array
			{
				$this->getPayloadCalled = true;

				return [];
			}
		};

		$nextCalled = false;
		$next = function ($job) use (&$nextCalled) {
			$nextCalled = true;

			return $job;
		};

		$middleware = new TrackJob($manager);
		$result = $middleware->handle($job, $next);

		$this->assertTrue($job->getPayloadCalled);
		$this->assertTrue($nextCalled);
		$this->assertSame($job, $result);
	}

	public function test_skips_tagging_when_job_has_no_get_payload_method(): void
	{
		$manager = $this->createMock(RetryManager::class);
		$manager->expects($this->never())
			->method('forJob');

		$job = new \stdClass();

		$nextCalled = false;
		$next = function ($job) use (&$nextCalled) {
			$nextCalled = true;

			return $job;
		};

		$middleware = new TrackJob($manager);
		$result = $middleware->handle($job, $next);

		$this->assertTrue($nextCalled);
		$this->assertSame($job, $result);
	}
}
