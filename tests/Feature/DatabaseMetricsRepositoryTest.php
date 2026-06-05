<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Tests\TestCase;

class DatabaseMetricsRepositoryTest extends TestCase
{
	private MetricsRepository $repo;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repo = app(MetricsRepository::class);
	}

	public function test_increment_processed_stores_record(): void
	{
		$minute = now()->startOfMinute()->format('Y-m-d H:i:s');
		DB::table('cauce_metrics')->insert([
			'connection' => 'database',
			'queue' => 'default',
			'minute' => $minute,
			'processed' => 0,
			'failed' => 0,
			'runtime_sum_ms' => 0,
			'runtime_count' => 0,
			'throughput' => 0,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$this->repo->increment('database', 'default', 'processed');

		$from = CarbonImmutable::now()->startOfMinute();
		$to = CarbonImmutable::now()->endOfMinute();
		$result = $this->repo->series('database', 'default', 'processed', $from, $to);

		$this->assertCount(1, $result);
		$this->assertSame(1, $result->first()->value);
	}

	public function test_increment_failed_stores_record(): void
	{
		$minute = now()->startOfMinute()->format('Y-m-d H:i:s');
		DB::table('cauce_metrics')->insert([
			'connection' => 'database',
			'queue' => 'default',
			'minute' => $minute,
			'processed' => 0,
			'failed' => 0,
			'runtime_sum_ms' => 0,
			'runtime_count' => 0,
			'throughput' => 0,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$this->repo->increment('database', 'default', 'failed', 3.0);

		$from = CarbonImmutable::now()->startOfMinute();
		$to = CarbonImmutable::now()->endOfMinute();
		$result = $this->repo->series('database', 'default', 'failed', $from, $to);

		$this->assertCount(1, $result);
		$this->assertSame(3, $result->first()->value);
	}

	public function test_increment_throughput_stores_record(): void
	{
		$minute = now()->startOfMinute()->format('Y-m-d H:i:s');
		DB::table('cauce_metrics')->insert([
			'connection' => 'redis',
			'queue' => 'high',
			'minute' => $minute,
			'processed' => 0,
			'failed' => 0,
			'runtime_sum_ms' => 0,
			'runtime_count' => 0,
			'throughput' => 0,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$this->repo->increment('redis', 'high', 'throughput', 2.5);

		$from = CarbonImmutable::now()->startOfMinute();
		$to = CarbonImmutable::now()->endOfMinute();
		$result = $this->repo->series('redis', 'high', 'throughput', $from, $to);

		$this->assertCount(1, $result);
		$this->assertSame(2.5, $result->first()->value);
	}

	public function test_increment_with_runtime_stores_runtime_data(): void
	{
		$minute = now()->startOfMinute()->format('Y-m-d H:i:s');
		DB::table('cauce_metrics')->insert([
			'connection' => 'database',
			'queue' => 'default',
			'minute' => $minute,
			'processed' => 0,
			'failed' => 0,
			'runtime_sum_ms' => 0,
			'runtime_count' => 0,
			'throughput' => 0,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$this->repo->increment('database', 'default', 'processed', 1.0, 150);

		$from = CarbonImmutable::now()->startOfMinute();
		$to = CarbonImmutable::now()->endOfMinute();
		$result = $this->repo->series('database', 'default', 'runtime_avg_ms', $from, $to);

		$this->assertCount(1, $result);
		$this->assertSame(150.0, $result->first()->value);
	}

	public function test_series_returns_empty_collection_for_no_data(): void
	{
		$from = CarbonImmutable::now()->subHour();
		$to = CarbonImmutable::now()->subHour()->addMinute();
		$result = $this->repo->series('nonexistent', 'queue', 'processed', $from, $to);

		$this->assertCount(0, $result);
	}

	public function test_totals_returns_aggregates(): void
	{
		$minute = now()->startOfMinute()->format('Y-m-d H:i:s');
		DB::table('cauce_metrics')->insert([
			'connection' => 'database',
			'queue' => 'default',
			'minute' => $minute,
			'processed' => 0,
			'failed' => 0,
			'runtime_sum_ms' => 0,
			'runtime_count' => 0,
			'throughput' => 0,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$this->repo->increment('database', 'default', 'processed', 1.0, 100);
		$this->repo->increment('database', 'default', 'processed', 1.0, 200);
		$this->repo->increment('database', 'default', 'failed');

		$from = CarbonImmutable::now()->startOfMinute();
		$to = CarbonImmutable::now()->endOfMinute();
		$totals = $this->repo->totals('database', 'default', $from, $to);

		$this->assertSame(2, $totals['processed']);
		$this->assertSame(1, $totals['failed']);
		$this->assertSame(150.0, $totals['runtime_avg_ms']);
		$this->assertGreaterThan(0.0, $totals['throughput_per_min']);
		$this->assertSame(round(2 / 3 * 100, 2), $totals['success_rate']);
	}

	public function test_totals_success_rate_zero_when_no_data(): void
	{
		$from = CarbonImmutable::now()->subHour();
		$to = CarbonImmutable::now()->subHour()->addMinute();
		$totals = $this->repo->totals('nonexistent', 'queue', $from, $to);

		$this->assertSame(0, $totals['processed']);
		$this->assertSame(0, $totals['failed']);
		$this->assertSame(0.0, $totals['runtime_avg_ms']);
		$this->assertSame(0.0, $totals['throughput_per_min']);
		$this->assertSame(0.0, $totals['success_rate']);
	}

	public function test_prune_deletes_old_records(): void
	{
		DB::table('cauce_metrics')->insert([
			'connection' => 'database',
			'queue' => 'default',
			'minute' => now()->subDays(2)->format('Y-m-d H:i:s'),
			'processed' => 10,
			'failed' => 2,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$deleted = $this->repo->prune(CarbonImmutable::now()->subDay());

		$this->assertSame(1, $deleted);
	}
}
