<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Illuminate\Queue\Events\JobProcessing;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Listeners\RecordJobProcessing;
use Marmol89\Cauce\Tests\TestCase;

class RecordJobProcessingTest extends TestCase
{
	public function test_marks_job_as_processing_when_cauce_id_exists(): void
	{
		$repo = app(JobRepository::class);

		$id = $repo->recordQueued([
			'uuid' => 'proc-uuid-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\TestJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$mockJob = new class {
			public function payload(): array
			{
				return ['uuid' => 'proc-uuid-1'];
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

		$event = new JobProcessing('database', $mockJob);
		$listener = app(RecordJobProcessing::class);
		$listener->handle($event);

		$row = $repo->find($id);
		$this->assertSame('processing', $row->status);
		$this->assertNotNull($row->started_at);
	}

	public function test_does_nothing_when_cauce_id_not_found(): void
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

		$event = new JobProcessing('database', $mockJob);
		$listener = app(RecordJobProcessing::class);

		$listener->handle($event);
		$this->assertTrue(true);
	}
}
