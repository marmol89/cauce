<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Listeners\RecordJobExceptionOccurred;
use Marmol89\Cauce\Tests\TestCase;

class RecordJobExceptionOccurredTest extends TestCase
{
	public function test_sets_status_to_retrying_when_exception_occurs(): void
	{
		$repo = app(JobRepository::class);

		$id = $repo->recordQueued([
			'uuid' => 'exc-uuid-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\TestJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$mockJob = new class {
			public function payload(): array
			{
				return ['uuid' => 'exc-uuid-1'];
			}
			public function resolveName(): string
			{
				return 'App\\Jobs\\TestJob';
			}
			public function getJobId()
			{
				return null;
			}
			public function hasFailed(): bool
			{
				return false;
			}
		};

		$exception = new \RuntimeException('test exception');

		$event = new JobExceptionOccurred('database', $mockJob, $exception);
		$listener = app(RecordJobExceptionOccurred::class);
		$listener->handle($event);

		$row = $repo->find($id);
		$this->assertSame('retrying', $row->status);
		$this->assertStringContainsString('RuntimeException', $row->exception);
	}

	public function test_skips_when_job_has_failed(): void
	{
		$repo = app(JobRepository::class);

		$id = $repo->recordQueued([
			'uuid' => 'failed-uuid-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\TestJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$mockJob = new class {
			public function payload(): array
			{
				return ['uuid' => 'failed-uuid-1'];
			}
			public function resolveName(): string
			{
				return 'App\\Jobs\\TestJob';
			}
			public function getJobId()
			{
				return null;
			}
			public function hasFailed(): bool
			{
				return true;
			}
		};

		$exception = new \RuntimeException('should be ignored');

		$event = new JobExceptionOccurred('database', $mockJob, $exception);
		$listener = app(RecordJobExceptionOccurred::class);
		$listener->handle($event);

		$row = $repo->find($id);
		$this->assertSame('queued', $row->status);
	}

	public function test_gracefully_handles_null_cauce_id(): void
	{
		$mockJob = new class {
			public function payload(): array
			{
				return ['uuid' => 'non-existent-uuid'];
			}
			public function resolveName(): string
			{
				return 'App\\Jobs\\TestJob';
			}
			public function getJobId()
			{
				return null;
			}
		};

		$exception = new \RuntimeException('test');

		$event = new JobExceptionOccurred('database', $mockJob, $exception);
		$listener = app(RecordJobExceptionOccurred::class);

		$listener->handle($event);
		$this->assertTrue(true);
	}
}
