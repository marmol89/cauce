<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Support;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobQueued;
use Symfony\Component\Uid\Ulid;

class JobPayloadExtractor
{
    public function ulid(): string
    {
        return (string) new Ulid();
    }

    /**
     * @return array<string, mixed>
     */
    public function fromQueued(JobQueued $event): array
    {
        $maxSize = (int) config('cauce.monitoring.payload_max_size', 65535);

        return [
            'uuid' => $event->id,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue() ?: 'default',
            'name' => $this->resolveJobName($event->job),
            'payload' => $this->safePayload($event->job->payload(), $maxSize),
            'tags' => $this->resolveTags($event->job),
            'queued_at' => now(),
            'status' => 'queued',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromProcessing(\Illuminate\Queue\Events\JobProcessing $event): array
    {
        return [
            'started_at' => now(),
            'status' => 'processing',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromProcessed(JobProcessed $event): array
    {
        return [
            'status' => 'completed',
            'finished_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromFailed(JobFailed $event): array
    {
        return [
            'status' => 'failed',
            'failed_at' => now(),
            'exception' => $this->normalizeException($event->exception),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromException(\Illuminate\Queue\Events\JobExceptionOccurred $event): array
    {
        return [
            'status' => 'retrying',
            'exception' => $this->normalizeException($event->exception),
        ];
    }

    protected function resolveJobName(object $job): string
    {
        if (method_exists($job, 'resolveName')) {
            return (string) $job->resolveName();
        }

        if (property_exists($job, 'displayName')) {
            return (string) $job->displayName;
        }

        return get_class($job);
    }

    protected function resolveTags(object $job): array
    {
        if (method_exists($job, 'tags')) {
            $tags = (array) $job->tags();

            return array_values(array_filter(array_map('strval', $tags), 'strlen'));
        }

        return [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    protected function safePayload(array $payload, int $maxSize): bool|array
    {
        if (! (bool) config('cauce.monitoring.store_payload', true)) {
            return null;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($encoded === false || strlen($encoded) > $maxSize) {
            return null;
        }

        return $payload;
    }

    protected function normalizeException(?\Throwable $exception): ?string
    {
        if ($exception === null) {
            return null;
        }

        $previous = [];

        while ($exception->getPrevious() !== null) {
            $exception = $exception->getPrevious();
            $previous[] = sprintf('%s: %s', $exception::class, $exception->getMessage());
        }

        $trace = implode("\n", $previous);

        return trim(sprintf(
            "%s: %s\n%s\n\n%s",
            $exception::class,
            $exception->getMessage(),
            $trace,
            $exception->getTraceAsString(),
        ));
    }
}
