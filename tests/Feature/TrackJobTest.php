<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Marmol89\Cauce\Middleware\TrackJob;
use Marmol89\Cauce\Tests\TestCase;

class TrackJobTest extends TestCase
{
	public function test_can_be_instantiated(): void
	{
		$middleware = new TrackJob();

		$this->assertInstanceOf(TrackJob::class, $middleware);
	}

	public function test_passes_job_through_to_next(): void
	{
		$job = new \stdClass();

		$nextCalled = false;
		$next = function ($job) use (&$nextCalled) {
			$nextCalled = true;

			return $job;
		};

		$middleware = new TrackJob();
		$result = $middleware->handle($job, $next);

		$this->assertTrue($nextCalled);
		$this->assertSame($job, $result);
	}

	public function test_passes_job_with_get_payload_through_to_next(): void
	{
		$job = new class {
			public function getPayload(): array
			{
				return [];
			}
		};

		$nextCalled = false;
		$next = function ($job) use (&$nextCalled) {
			$nextCalled = true;

			return $job;
		};

		$middleware = new TrackJob();
		$result = $middleware->handle($job, $next);

		$this->assertTrue($nextCalled);
		$this->assertSame($job, $result);
	}
}
