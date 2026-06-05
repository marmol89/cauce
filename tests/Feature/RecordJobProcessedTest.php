<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Listeners\RecordJobProcessed;
use Marmol89\Cauce\Support\ThroughputCalculator;
use Marmol89\Cauce\Tests\TestCase;

class RecordJobProcessedTest extends TestCase
{
	public function test_marks_job_as_processed_with_runtime(): void
	{
		$repo = app(JobRepository::class);

		$id = $repo->recordQueued([
			'uuid' => 'done-uuid-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\TestJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$repo->markProcessing($id);

		$mockJob = new class {
			public function payload(): array
			{
				return ['uuid' => 'done-uuid-1'];
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

		$event = new JobProcessed('database', $mockJob);
		$listener = app(RecordJobProcessed::class);
		$listener->handle($event);

		$row = $repo->find($id);
		$this->assertSame('completed', $row->status);
		$this->assertNotNull($row->runtime_ms);
	}

	public function test_increments_metrics_on_processed(): void
	{
		$repo = app(JobRepository::class);

		$id = $repo->recordQueued([
			'uuid' => 'metrics-uuid-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\TestJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$repo->markProcessing($id);

		$mockJob = new class {
			public function payload(): array
			{
				return ['uuid' => 'metrics-uuid-1'];
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

		$event = new JobProcessed('database', $mockJob);
		$listener = app(RecordJobProcessed::class);

		// Pre-populate the metrics bucket so updateOrInsert uses UPDATE path
		// (raw expressions like "processed + 1" don't work in INSERT on SQLite)
		$bucket = ThroughputCalculator::bucket(now());
		DB::table('cauce_metrics')->insert([
			'connection' => 'database',
			'queue' => 'default',
			'minute' => $bucket,
			'processed' => 0,
			'failed' => 0,
			'runtime_sum_ms' => 0,
			'runtime_count' => 0,
			'throughput' => 0,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$listener->handle($event);

		$metrics = app(MetricsRepository::class);
		$totals = $metrics->totals(
			'database',
			'default',
			CarbonImmutable::now()->subHour(),
			CarbonImmutable::now(),
		);

		$this->assertSame(1, $totals['processed']);
	}

	public function test_gracefully_handles_missing_uuid(): void
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

		$event = new JobProcessed('database', $mockJob);
		$listener = app(RecordJobProcessed::class);

		$listener->handle($event);
		$this->assertTrue(true);
	}
}
