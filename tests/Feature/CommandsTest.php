<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Tests\Fixtures\TestJob;
use Marmol89\Cauce\Tests\TestCase;

class CommandsTest extends TestCase
{
	public function test_retry_fails_when_job_not_found(): void
	{
		$this->artisan('cauce:retry', ['id' => 'nonexistent'])
			->assertFailed();
	}

	public function test_retry_requeues_job(): void
	{
		Queue::fake();

		$repo = app(JobRepository::class);

		$id = $repo->recordQueued([
			'uuid' => 'retry-test',
			'connection' => 'sync',
			'queue' => 'default',
			'name' => TestJob::class,
			'status' => 'queued',
			'queued_at' => now(),
			'payload' => [
				'uuid' => 'retry-test',
				'displayName' => TestJob::class,
				'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
				'data' => [
					'commandName' => TestJob::class,
					'command' => serialize(new TestJob()),
				],
			],
		]);
		$repo->markFailed($id, 'error');

		$this->artisan('cauce:retry', ['id' => $id])
			->expectsConfirmation('Dispatch this job again?', 'yes')
			->assertSuccessful();

		$row = $repo->find($id);
		$this->assertSame('queued', $row->status);
	}

	public function test_retry_passes_queue_and_connection_options(): void
	{
		$id = '01J1ABC0000000000000000000';

		$mock = $this->mock(JobRepository::class, function ($mock) use ($id) {
			$mock->shouldReceive('find')
				->once()
				->with($id)
				->andReturn((object) [
					'id' => $id,
					'name' => TestJob::class,
					'connection' => 'sync',
					'queue' => 'default',
				]);

			$mock->shouldReceive('retry')
				->once()
				->with($id, 'redis-test', 'high-test')
				->andReturn(true);
		});

		$this->artisan('cauce:retry', [
			'id' => $id,
			'--queue' => 'high-test',
			'--connection' => 'redis-test',
		])
			->expectsConfirmation('Dispatch this job again?', 'yes')
			->assertSuccessful();
	}

	public function test_prune_removes_old_records(): void
	{
		$repo = app(JobRepository::class);

		$id = $repo->recordQueued([
			'uuid' => 'old-completed',
			'connection' => 'sync',
			'queue' => 'default',
			'name' => TestJob::class,
			'status' => 'queued',
			'queued_at' => now()->subDays(2),
		]);
		$repo->markProcessing($id);
		$repo->markProcessed($id, 10);

		DB::table('cauce_jobs')
			->where('id', $id)
			->update(['finished_at' => now()->subDays(2)->format('Y-m-d H:i:s')]);

		$fid = $repo->recordQueued([
			'uuid' => 'old-failed',
			'connection' => 'sync',
			'queue' => 'default',
			'name' => TestJob::class,
			'status' => 'queued',
			'queued_at' => now()->subDays(2),
		]);
		$repo->markFailed($fid, 'boom');

		DB::table('cauce_jobs')
			->where('id', $fid)
			->update(['failed_at' => now()->subDays(2)->format('Y-m-d H:i:s')]);

		$this->artisan('cauce:prune', [
			'--completed' => '1',
			'--failed' => '1',
			'--force' => true,
		])->assertSuccessful();

		$this->assertNull($repo->find($id));
		$this->assertNull($repo->find($fid));
	}

	public function test_prune_keeps_recent_records(): void
	{
		$repo = app(JobRepository::class);

		$id = $repo->recordQueued([
			'uuid' => 'recent-completed',
			'connection' => 'sync',
			'queue' => 'default',
			'name' => TestJob::class,
			'status' => 'queued',
			'queued_at' => now(),
		]);
		$repo->markProcessing($id);
		$repo->markProcessed($id, 10);

		$fid = $repo->recordQueued([
			'uuid' => 'recent-failed',
			'connection' => 'sync',
			'queue' => 'default',
			'name' => TestJob::class,
			'status' => 'queued',
			'queued_at' => now(),
		]);
		$repo->markFailed($fid, 'boom');

		$this->artisan('cauce:prune', [
			'--completed' => '2',
			'--failed' => '2',
			'--force' => true,
		])->assertSuccessful();

		$this->assertNotNull($repo->find($id));
		$this->assertNotNull($repo->find($fid));
	}

	public function test_clear_jobs(): void
	{
		$repo = app(JobRepository::class);

		$repo->recordQueued([
			'uuid' => 'clear-jobs',
			'connection' => 'sync',
			'queue' => 'default',
			'name' => TestJob::class,
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$this->assertGreaterThan(0, DB::table('cauce_jobs')->count());

		$this->artisan('cauce:clear', ['--jobs' => true, '--force' => true])
			->assertSuccessful();

		$this->assertSame(0, DB::table('cauce_jobs')->count());
	}

	public function test_clear_metrics(): void
	{
		DB::table('cauce_metrics')->insert([
			'connection' => 'sync',
			'queue' => 'default',
			'minute' => now()->startOfMinute()->format('Y-m-d H:i:s'),
			'processed' => 5,
			'failed' => 1,
			'runtime_sum_ms' => 0,
			'runtime_count' => 0,
			'throughput' => 0,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$this->assertGreaterThan(0, DB::table('cauce_metrics')->count());

		$this->artisan('cauce:clear', ['--metrics' => true, '--force' => true])
			->assertSuccessful();

		$this->assertSame(0, DB::table('cauce_metrics')->count());
	}

	public function test_clear_all(): void
	{
		$repo = app(JobRepository::class);

		$repo->recordQueued([
			'uuid' => 'clear-all',
			'connection' => 'sync',
			'queue' => 'default',
			'name' => TestJob::class,
			'status' => 'queued',
			'queued_at' => now(),
		]);

		DB::table('cauce_metrics')->insert([
			'connection' => 'sync',
			'queue' => 'default',
			'minute' => now()->startOfMinute()->format('Y-m-d H:i:s'),
			'processed' => 3,
			'failed' => 0,
			'runtime_sum_ms' => 0,
			'runtime_count' => 0,
			'throughput' => 0,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$this->assertGreaterThan(0, DB::table('cauce_jobs')->count());
		$this->assertGreaterThan(0, DB::table('cauce_metrics')->count());

		$this->artisan('cauce:clear', ['--all' => true, '--force' => true])
			->assertSuccessful();

		$this->assertSame(0, DB::table('cauce_jobs')->count());
		$this->assertSame(0, DB::table('cauce_metrics')->count());
	}

	public function test_clear_without_options_shows_warning(): void
	{
		$this->artisan('cauce:clear')
			->assertFailed();
	}

	public function test_status_runs(): void
	{
		$this->artisan('cauce:status')
			->assertSuccessful();
	}
}
