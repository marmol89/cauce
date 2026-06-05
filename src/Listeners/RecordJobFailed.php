<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Support\DeadLetterManager;
use Marmol89\Cauce\Support\JobPayloadExtractor;

class RecordJobFailed
{
    public function __construct(
        protected JobRepository $jobs,
        protected MetricsRepository $metrics,
        protected JobPayloadExtractor $extractor,
    ) {
    }

    public function handle(JobFailed $event): void
    {
        try {
            $exceptionData = $this->extractor->fromFailed($event);

            $row = $this->resolveRow($event);

            if ($row !== null) {
                $this->jobs->markFailed($row->id, $exceptionData['exception']);

                $this->metrics->increment(
                    connection: $row->connection,
                    queue: $row->queue,
                    metric: 'failed',
                );

                if (DeadLetterManager::shouldSend()) {
                    try {
                        DeadLetterManager::send($row);
                    } catch (\Throwable) {
                        // DLQ delivery is best-effort; don't crash the listener.
                    }
                }

                return;
            }

            // No cauce id — fall back to creating a fresh row so the failure is
            // still tracked.
            $this->jobs->recordQueued([
                'uuid' => method_exists($event->job, 'uuid') ? $event->job->uuid() : ($event->job->getJobId() ?: null),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue() ?: 'default',
                'name' => method_exists($event->job, 'resolveName') ? $event->job->resolveName() : get_class($event->job),
                'status' => 'failed',
                'attempts' => (int) $event->job->attempts(),
                'exception' => $exceptionData['exception'],
                'failed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cauce: failed to record failed job', [
                'job' => $event->job->resolveName() ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveRow(JobFailed $event): ?object
    {
        $payload = $event->job->payload();
        $uuid = is_array($payload) ? ($payload['uuid'] ?? null) : null;
        $uuid ??= $event->job->getJobId();

        if ($uuid === null || $uuid === '') {
            return null;
        }

        return $this->jobs->findRowByUuid((string) $uuid);
    }
}
