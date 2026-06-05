<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;
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
            $cauceId = $this->resolveCauceId($event);

            $exceptionData = $this->extractor->fromFailed($event);

            if ($cauceId !== null) {
                $this->jobs->markFailed($cauceId, $exceptionData['exception']);

                $row = $this->jobs->find($cauceId);

                if ($row !== null) {
                    $this->metrics->increment(
                        connection: $row->connection,
                        queue: $row->queue,
                        metric: 'failed',
                    );
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
                'queued_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cauce: failed to record failed job', [
                'job' => $event->job->resolveName() ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveCauceId(JobFailed $event): ?string
    {
        $uuid = $event->job->payload()['uuid'] ?? $event->job->getJobId();

        if ($uuid === null || $uuid === '') {
            return null;
        }

        return $this->jobs->findIdByUuid((string) $uuid);
    }
}
