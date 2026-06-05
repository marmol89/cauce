<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Http\Livewire\FailedJobsTable;
use Marmol89\Cauce\Http\Livewire\JobsTable;
use Marmol89\Cauce\Http\Livewire\MetricsChart;
use Marmol89\Cauce\Http\Livewire\QueueStats;
use Marmol89\Cauce\Tests\TestCase;

class LivewireJobsTableTest extends TestCase
{
	protected function getPackageProviders($app): array
	{
		return array_merge(parent::getPackageProviders($app), [
			LivewireServiceProvider::class,
		]);
	}

	public function test_jobs_table_renders(): void
	{
		Livewire::test(JobsTable::class)->assertOk();
	}

	public function test_jobs_table_shows_jobs(): void
	{
		$repo = app(JobRepository::class);

		$repo->recordQueued([
			'uuid' => 'job-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\SendEmail',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$component = Livewire::test(JobsTable::class);

		$component->assertOk();
		$component->assertSee('SendEmail');
		$this->assertNotEmpty($component->jobs);
	}

	public function test_jobs_table_filter_by_status(): void
	{
		$repo = app(JobRepository::class);

		$completedId = $repo->recordQueued([
			'uuid' => 'done-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\DoneJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);
		$repo->markProcessed($completedId, 10);

		$failedId = $repo->recordQueued([
			'uuid' => 'fail-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\FailJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);
		$repo->markFailed($failedId, 'error');

		$component = Livewire::test(JobsTable::class);
		$component->set('status', 'completed');

		$component->assertOk();
		$component->assertSee('DoneJob');
		$component->assertDontSee('FailJob');
	}

	public function test_jobs_table_search(): void
	{
		$repo = app(JobRepository::class);

		$repo->recordQueued([
			'uuid' => 'alpha-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\AlphaJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$repo->recordQueued([
			'uuid' => 'beta-1',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\BetaJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$component = Livewire::test(JobsTable::class);
		$component->set('search', 'Alpha');

		$component->assertOk();
		$component->assertSee('AlphaJob');
		$component->assertDontSee('BetaJob');
	}

	public function test_jobs_table_pagination_respects_per_page(): void
	{
		$repo = app(JobRepository::class);

		for ($i = 0; $i < 30; $i++) {
			$repo->recordQueued([
				'uuid' => "page-$i",
				'connection' => 'database',
				'queue' => 'default',
				'name' => "App\\Jobs\\Job$i",
				'status' => 'queued',
				'queued_at' => now(),
			]);
		}

		$component = Livewire::test(JobsTable::class);
		$component->set('perPage', 5);

		$component->assertOk();
		$this->assertCount(5, $component->jobs);
	}

	public function test_failed_jobs_table_renders(): void
	{
		Livewire::test(FailedJobsTable::class)->assertOk();
	}

	public function test_failed_jobs_table_shows_failed_jobs(): void
	{
		$repo = app(JobRepository::class);

		$failedId = $repo->recordQueued([
			'uuid' => 'fail-show',
			'connection' => 'database',
			'queue' => 'default',
			'name' => 'App\\Jobs\\BoomJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);
		$repo->markFailed($failedId, 'RuntimeException: boom');

		$component = Livewire::test(FailedJobsTable::class);

		$component->assertOk();
		$component->assertSee('BoomJob');
		$this->assertNotEmpty($component->jobs);
	}

	public function test_metrics_chart_renders(): void
	{
		Livewire::test(MetricsChart::class, ['hours' => 6])->assertOk();
	}

	public function test_metrics_chart_validates_hours(): void
	{
		$component = Livewire::test(MetricsChart::class, ['hours' => -5]);
		$this->assertSame(1, $component->hours);

		$component = Livewire::test(MetricsChart::class, ['hours' => 200]);
		$this->assertSame(168, $component->hours);

		$component = Livewire::test(MetricsChart::class, ['hours' => 24]);
		$this->assertSame(24, $component->hours);

		$component = Livewire::test(MetricsChart::class, ['hours' => 10]);
		$component->set('hours', 999);
		$this->assertSame(168, $component->hours);

		$component->set('hours', 0);
		$this->assertSame(1, $component->hours);
	}

	public function test_queue_stats_renders(): void
	{
		Livewire::test(QueueStats::class)->assertOk();
	}

	public function test_queue_stats_shows_counts(): void
	{
		$repo = app(JobRepository::class);

		$repo->recordQueued([
			'uuid' => 'qs-1',
			'connection' => 'redis',
			'queue' => 'high',
			'name' => 'App\\Jobs\\FastJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$repo->recordQueued([
			'uuid' => 'qs-2',
			'connection' => 'redis',
			'queue' => 'high',
			'name' => 'App\\Jobs\\AnotherJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$repo->recordQueued([
			'uuid' => 'qs-3',
			'connection' => 'database',
			'queue' => 'low',
			'name' => 'App\\Jobs\\SlowJob',
			'status' => 'queued',
			'queued_at' => now(),
		]);

		$component = Livewire::test(QueueStats::class);

		$component->assertOk();
		$component->assertSee('redis');
		$component->assertSee('high');
		$component->assertSee('database');
		$component->assertSee('low');
	}
}
