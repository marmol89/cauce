<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface JobRepository
{
    public function recordQueued(array $data): string;

    public function markProcessing(string $cauceId): void;

    public function markProcessed(string $cauceId, int $runtimeMs): void;

    public function markFailed(string $cauceId, ?string $exception = null): void;

    public function recordException(string $cauceId, string $exception): void;

    public function find(string $cauceId): ?object;

    public function findIdByUuid(string $uuid): ?string;

    public function findRowByUuid(string $uuid): ?object;

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function failed(int $perPage = 25): LengthAwarePaginator;

    public function retry(string $cauceId, ?string $connection = null, ?string $queue = null): bool;

    public function delete(string $cauceId): bool;

    public function pruneCompleted(CarbonImmutable $before): int;

    public function pruneFailed(CarbonImmutable $before): int;

    public function countsByStatus(int $hours = 24): Collection;

    public function countsByConnection(int $hours = 24): Collection;

    public function countsByQueue(int $hours = 24): Collection;

    public function distinctConnections(): Collection;

    public function distinctQueues(): Collection;

    public function distinctTags(): Collection;
}
