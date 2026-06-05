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

        $columns = [
            'connection' => $connection,
            'queue' => $queue,
            'minute' => $minute,
            'updated_at' => now(),
        ];

        $update = [];

        switch ($metric) {
            case 'processed':
                $update = [
                    'processed' => $this->connection->raw('processed + 1'),
                    'runtime_sum_ms' => $this->connection->raw('runtime_sum_ms + ' . (int) ($runtimeMs ?? 0)),
                    'runtime_count' => $this->connection->raw('runtime_count + 1'),
                    'updated_at' => now(),
                ];
                break;

            case 'failed':
                $update = [
                    'failed' => $this->connection->raw('failed + ' . (int) $value),
                    'updated_at' => now(),
                ];
                break;

            case 'throughput':
                $update = [
                    'throughput' => $this->connection->raw('throughput + ' . (float) $value),
                    'updated_at' => now(),
                ];
                break;
        }

        $this->connection->table($this->table)->updateOrInsert(
            $columns,
            array_merge($update, [
                'created_at' => now(),
            ]),
        );
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

    protected function successRate(int $processed, int $failed): float
    {
        $total = $processed + $failed;

        if ($total === 0) {
            return 0.0;
        }

        return round($processed / $total * 100, 2);
    }
}
