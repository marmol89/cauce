<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Support\ThroughputCalculator;

class DatabaseMetricsRepository implements MetricsRepository
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected string $table = 'cauce_metrics',
    ) {
    }

    public function increment(string $connection, string $queue, string $metric, float $value = 1.0, ?int $runtimeMs = null): void
    {
        $minute = ThroughputCalculator::bucket(now());
        $driver = $this->connection->getDriverName();

        if ($driver === 'mysql') {
            $this->incrementMysql($connection, $queue, $minute, $metric, $value, $runtimeMs);
        } else {
            $this->incrementUpsert($connection, $queue, $minute, $metric, $value, $runtimeMs);
        }
    }

    protected function incrementMysql(string $connection, string $queue, \DateTimeImmutable $minute, string $metric, float $value, ?int $runtimeMs): void
    {
        $processed = 0;
        $failed = 0;
        $throughput = 0.0;
        $rSum = 0;
        $rCount = 0;

        if ($metric === 'processed') {
            $processed = 1;
            $rSum = (int) ($runtimeMs ?? 0);
            $rCount = 1;
        } elseif ($metric === 'failed') {
            $failed = (int) $value;
        } elseif ($metric === 'throughput') {
            $throughput = $value;
        }

        $this->connection->statement("
            INSERT INTO {$this->table} (connection, queue, minute, processed, failed, runtime_sum_ms, runtime_count, throughput, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                processed = processed + VALUES(processed),
                failed = failed + VALUES(failed),
                throughput = throughput + VALUES(throughput),
                runtime_sum_ms = runtime_sum_ms + VALUES(runtime_sum_ms),
                runtime_count = runtime_count + VALUES(runtime_count),
                updated_at = NOW()
        ", [$connection, $queue, $minute, $processed, $failed, $rSum, $rCount, $throughput]);
    }

    protected function incrementUpsert(string $connection, string $queue, \DateTimeImmutable $minute, string $metric, float $value, ?int $runtimeMs): void
    {
        $this->connection->transaction(function () use ($connection, $queue, $minute, $metric, $value, $runtimeMs) {
            $existing = $this->connection->table($this->table)
                ->where('connection', $connection)
                ->where('queue', $queue)
                ->where('minute', $minute)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $update = ['updated_at' => now()];

                if ($metric === 'processed') {
                    $update['processed'] = $this->connection->raw('processed + 1');
                    $update['runtime_sum_ms'] = $this->connection->raw('runtime_sum_ms + ' . (int) ($runtimeMs ?? 0));
                    $update['runtime_count'] = $this->connection->raw('runtime_count + 1');
                } elseif ($metric === 'failed') {
                    $update['failed'] = $this->connection->raw('failed + ' . (int) $value);
                } elseif ($metric === 'throughput') {
                    $update['throughput'] = $this->connection->raw('throughput + ' . (float) $value);
                }

                $this->connection->table($this->table)
                    ->where('id', $existing->id)
                    ->update($update);
            } else {
                $insert = [
                    'connection' => $connection,
                    'queue' => $queue,
                    'minute' => $minute,
                    'processed' => $metric === 'processed' ? 1 : 0,
                    'failed' => $metric === 'failed' ? (int) $value : 0,
                    'runtime_sum_ms' => $metric === 'processed' ? (int) ($runtimeMs ?? 0) : 0,
                    'runtime_count' => $metric === 'processed' ? 1 : 0,
                    'throughput' => $metric === 'throughput' ? $value : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $this->connection->table($this->table)->insert($insert);
            }
        });
    }

    public function series(string $connection, string $queue, string $metric, CarbonImmutable $from, CarbonImmutable $to, int $bucket = 60): Collection
    {
        $rows = $this->connection->table($this->table)
            ->where('connection', $connection)
            ->where('queue', $queue)
            ->whereBetween('minute', [$from, $to])
            ->orderBy('minute')
            ->get();

        return $rows->map(function ($row) use ($metric) {
            return (object) [
                'minute' => $row->minute,
                'value' => match ($metric) {
                    'processed' => (int) $row->processed,
                    'failed' => (int) $row->failed,
                    'throughput' => (float) $row->throughput,
                    'runtime_avg_ms' => $row->runtime_count > 0
                        ? round((float) $row->runtime_sum_ms / (int) $row->runtime_count, 2)
                        : 0.0,
                    default => 0,
                },
            ];
        });
    }

    public function totals(string $connection, string $queue, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->connection->table($this->table)
            ->where('connection', $connection)
            ->where('queue', $queue)
            ->whereBetween('minute', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(processed), 0) as processed,
                COALESCE(SUM(failed), 0) as failed,
                COALESCE(SUM(runtime_sum_ms), 0) as runtime_sum_ms,
                COALESCE(SUM(runtime_count), 0) as runtime_count
            ')
            ->first();

        $runtimeAvg = $row && (int) $row->runtime_count > 0
            ? round((float) $row->runtime_sum_ms / (int) $row->runtime_count, 2)
            : 0.0;

        $minutes = max(1, $to->diffInMinutes($from));
        $throughput = $row ? round((int) $row->processed / $minutes, 2) : 0.0;

        return [
            'processed' => (int) ($row->processed ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'runtime_avg_ms' => $runtimeAvg,
            'throughput_per_min' => $throughput,
            'success_rate' => $this->successRate((int) ($row->processed ?? 0), (int) ($row->failed ?? 0)),
        ];
    }

    public function prune(CarbonImmutable $before): int
    {
        return $this->connection->table($this->table)
            ->where('minute', '<', $before)
            ->delete();
    }

    public function globalTotals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->connection->table($this->table)
            ->whereBetween('minute', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(processed), 0) as processed,
                COALESCE(SUM(failed), 0) as failed,
                COALESCE(SUM(runtime_sum_ms), 0) as runtime_sum_ms,
                COALESCE(SUM(runtime_count), 0) as runtime_count
            ')
            ->first();

        $processed = (int) ($row->processed ?? 0);
        $failed = (int) ($row->failed ?? 0);
        $runtimeAvg = $row && (int) $row->runtime_count > 0
            ? round((float) $row->runtime_sum_ms / (int) $row->runtime_count, 2)
            : 0.0;

        $minutes = max(1, $to->diffInMinutes($from));
        $throughput = $row ? round($processed / $minutes, 2) : 0.0;

        return [
            'processed' => $processed,
            'failed' => $failed,
            'runtime_avg_ms' => $runtimeAvg,
            'throughput_per_min' => $throughput,
            'success_rate' => $this->successRate($processed, $failed),
        ];
    }

    protected function successRate(int $processed, int $failed): float
    {
        $total = $processed + $failed;

        if ($total === 0) {
            return 0.0;
        }

        return round($processed / $total * 100, 2);
    }
}
