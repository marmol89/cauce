<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Listeners;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Log;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;

class RecordJobProcessed
{
    public function __construct(
        protected JobRepository $jobs,
        protected MetricsRepository $metrics,
    ) {
    }

    public function handle(JobProcessed $event): void
    {
        try {
            $cauceId = $this->resolveCauceId($event);

            if ($cauceId === null) {
                return;
            }

            $row = $this->jobs->find($cauceId);

            if ($row === null) {
                return;
            }

            $runtimeMs = $this->runtimeMs($row);

            $this->jobs->markProcessed($cauceId, $runtimeMs);

            $this->metrics->increment(
                connection: $row->connection,
                queue: $row->queue,
                metric: 'processed',
                value: 1.0,
                runtimeMs: $runtimeMs,
            );
        } catch (\Throwable $e) {
            Log::warning('Cauce: failed to record processed job', [
                'job' => $event->job->resolveName() ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveCauceId(JobProcessed $event): ?string
    {
        $payload = method_exists($event->job, 'getPayload') ? $event->job->getPayload() : [];

        return $payload['cauce_id'] ?? null;
    }

    protected function runtimeMs(object $row): int
    {
        if ($row->started_at === null) {
            return 0;
        }

        return (int) round((microtime(true) - strtotime((string) $row->started_at)) * 1000);
    }
}
