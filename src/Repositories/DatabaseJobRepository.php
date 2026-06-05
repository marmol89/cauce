<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Events\JobRetried;

class DatabaseJobRepository implements JobRepository
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected string $table = 'cauce_jobs',
    ) {
    }

    public function recordQueued(array $data): string
    {
        $cauceId = (string) ($data['cauce_id'] ?? $this->newUlid());
        $data['id'] = $cauceId;
        $data['created_at'] = $data['created_at'] ?? now();
        $data['updated_at'] = $data['updated_at'] ?? now();
        $data['attempts'] = (int) ($data['attempts'] ?? 0);

        unset($data['cauce_id']);

        $data = $this->encodeJsonColumns($data);

        $this->connection->table($this->table)->insert($data);

        return $cauceId;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function encodeJsonColumns(array $data): array
    {
        foreach (['payload', 'tags'] as $field) {
            if (array_key_exists($field, $data) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
            }
        }

        return $data;
    }

    protected function decodeJsonColumns(object $row): object
    {
        foreach (['payload', 'tags'] as $field) {
            if (isset($row->$field) && is_string($row->$field)) {
                $decoded = json_decode($row->$field, true);
                $row->$field = is_array($decoded) ? $decoded : null;
            }
        }

        return $this->castDates($row);
    }

    protected function castDates(object $row): object
    {
        foreach (['created_at', 'updated_at', 'started_at', 'finished_at', 'failed_at', 'available_at'] as $field) {
            if (isset($row->$field) && is_string($row->$field)) {
                $row->$field = CarbonImmutable::parse($row->$field);
            }
        }

        return $row;
    }

    public function markProcessing(string $cauceId): void
    {
        $this->connection->table($this->table)
            ->where('id', $cauceId)
            ->update([
                'status' => 'processing',
                'started_at' => now(),
                'attempts' => $this->connection->raw('attempts + 1'),
                'updated_at' => now(),
            ]);
    }

    public function markProcessed(string $cauceId, int $runtimeMs): void
    {
        $this->connection->table($this->table)
            ->where('id', $cauceId)
            ->update([
                'status' => 'completed',
                'finished_at' => now(),
                'runtime_ms' => $runtimeMs,
                'exception' => null,
                'updated_at' => now(),
            ]);
    }

    public function markFailed(string $cauceId, ?string $exception = null): void
    {
        $this->connection->table($this->table)
            ->where('id', $cauceId)
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'exception' => $exception,
                'updated_at' => now(),
            ]);
    }

    public function recordException(string $cauceId, string $exception): void
    {
        $this->connection->table($this->table)
            ->where('id', $cauceId)
            ->update([
                'status' => 'retrying',
                'exception' => $exception,
                'updated_at' => now(),
            ]);
    }

    public function find(string $cauceId): ?object
    {
        $row = $this->connection->table($this->table)
            ->where('id', $cauceId)
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->decodeJsonColumns($row);
    }

    public function findIdByUuid(string $uuid): ?string
    {
        $id = $this->connection->table($this->table)
            ->where('uuid', $uuid)
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (string) $id : null;
    }

    public function findRowByUuid(string $uuid): ?object
    {
        $row = $this->connection->table($this->table)
            ->where('uuid', $uuid)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->decodeJsonColumns($row);
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->connection->table($this->table)->orderByDesc('id');

        $this->applyFilters($query, $filters);

        $paginator = $query->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($row) => $this->decodeJsonColumns($row))
        );

        return $paginator;
    }

    public function failed(int $perPage = 25): LengthAwarePaginator
    {
        $paginator = $this->connection->table($this->table)
            ->where('status', 'failed')
            ->orderByDesc('failed_at')
            ->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($row) => $this->decodeJsonColumns($row))
        );

        return $paginator;
    }

    public function retry(string $cauceId, ?string $connection = null, ?string $queue = null): bool
    {
        $row = $this->find($cauceId);

        if ($row === null) {
            return false;
        }

        $payload = $this->preparePayload($row);

        if ($payload === null) {
            return false;
        }

        $this->connection->table($this->table)
            ->where('id', $cauceId)
            ->update([
                'status' => 'queued',
                'exception' => null,
                'failed_at' => null,
                'finished_at' => null,
                'started_at' => null,
                'attempts' => 0,
                'updated_at' => now(),
            ]);

        try {
            Queue::connection($connection ?: $row->connection)
                ->pushRaw($payload, $queue ?: $row->queue);
        } catch (\Throwable) {
            $this->connection->table($this->table)
                ->where('id', $cauceId)
                ->update([
                    'status' => 'failed',
                    'exception' => $row->exception ?? null,
                    'failed_at' => $row->failed_at ?? now(),
                    'updated_at' => now(),
                ]);

            return false;
        }

        JobRetried::dispatch($cauceId, $row->name, $row->connection, $row->queue);

        return true;
    }

    protected function preparePayload(object $row): ?string
    {
        $payload = $row->payload;

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (! is_array($payload) || empty($payload['data']['commandName'])) {
            return null;
        }

        if (! class_exists($payload['data']['commandName'])) {
            return null;
        }

        $payload['attempts'] = 0;
        $payload['uuid'] = (string) (\Symfony\Component\Uid\Uuid::v4());

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    public function delete(string $cauceId): bool
    {
        return (bool) $this->connection->table($this->table)
            ->where('id', $cauceId)
            ->delete();
    }

    public function pruneCompleted(CarbonImmutable $before): int
    {
        return $this->connection->table($this->table)
            ->whereIn('status', ['completed', 'queued', 'retrying'])
            ->where(function ($query) use ($before) {
                $query->where(function ($q) use ($before) {
                    $q->whereNotNull('finished_at')
                      ->where('finished_at', '<', $before);
                })->orWhere(function ($q) use ($before) {
                    $q->whereNull('finished_at')
                      ->where('created_at', '<', $before);
                });
            })
            ->delete();
    }

    public function pruneFailed(CarbonImmutable $before): int
    {
        return $this->connection->table($this->table)
            ->where('status', 'failed')
            ->where('failed_at', '<', $before)
            ->delete();
    }

    public function countsByStatus(int $hours = 24): Collection
    {
        return $this->connection->table($this->table)
            ->select('status', $this->connection->raw('COUNT(*) as total'))
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');
    }

    public function countsByConnection(int $hours = 24): Collection
    {
        return $this->connection->table($this->table)
            ->select('connection', $this->connection->raw('COUNT(*) as total'))
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('connection')
            ->get()
            ->pluck('total', 'connection');
    }

    public function countsByQueue(int $hours = 24): Collection
    {
        return $this->connection->table($this->table)
            ->select('connection', 'queue', $this->connection->raw('COUNT(*) as total'))
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('connection', 'queue')
            ->get()
            ->mapWithKeys(function ($row) {
                return [sprintf('%s::%s', $row->connection, $row->queue) => (int) $row->total];
            });
    }

    public function distinctConnections(): Collection
    {
        return $this->connection->table($this->table)
            ->select('connection')
            ->distinct()
            ->orderBy('connection')
            ->pluck('connection');
    }

    public function distinctQueues(): Collection
    {
        return $this->connection->table($this->table)
            ->select('queue')
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue');
    }

    public function distinctTags(): Collection
    {
        return Cache::remember('cauce:distinct_tags', 300, function () {
            $rows = $this->connection->table($this->table)
                ->whereNotNull('tags')
                ->select('tags')
                ->distinct()
                ->limit(1000)
                ->get();

            $tags = [];
            foreach ($rows as $row) {
                $decoded = json_decode($row->tags, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $tag) {
                        $tags[$tag] = true;
                    }
                }
            }

            return collect(array_keys($tags))->sort()->values();
        });
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['connection'])) {
            $query->where('connection', $filters['connection']);
        }

        if (! empty($filters['queue'])) {
            $query->where('queue', $filters['queue']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (! empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        if (! empty($filters['chain_id'])) {
            $query->where('chain_id', $filters['chain_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['tags'])) {
            $tags = (array) $filters['tags'];
            foreach ($tags as $tag) {
                $query->whereRaw(
                    'JSON_CONTAINS(tags, ?)',
                    [json_encode($tag, JSON_UNESCAPED_UNICODE)],
                );
            }
        }
    }

    protected function newUlid(): string
    {
        return (string) (\Symfony\Component\Uid\Ulid::generate());
    }
}
