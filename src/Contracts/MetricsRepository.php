<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface MetricsRepository
{
    public function increment(string $connection, string $queue, string $metric, float $value = 1.0, ?int $runtimeMs = null): void;

    public function series(string $connection, string $queue, string $metric, CarbonImmutable $from, CarbonImmutable $to, int $bucket = 60): Collection;

    public function totals(string $connection, string $queue, CarbonImmutable $from, CarbonImmutable $to): array;

    public function prune(CarbonImmutable $before): int;

    public function globalTotals(CarbonImmutable $from, CarbonImmutable $to): array;
}
