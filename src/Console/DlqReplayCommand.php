<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class DlqReplayCommand extends Command
{
    protected $signature = 'cauce:dlq-replay
                            {--connection= : Connection the DLQ lives on}
                            {--queue= : Queue the DLQ lives on}
                            {--limit=50 : Max number of jobs to replay}
                            {--force : Skip confirmation}';

    protected $description = 'Replay jobs from the dead-letter queue back into the main queue.';

    public function handle(): int
    {
        $connection = $this->option('connection') ?? config('queue.default');
        $dlqQueue = $this->option('queue') ?? config('cauce.dead_letter.queue', 'dead-letter');
        $limit = (int) $this->option('limit');

        $this->components->info("Replaying up to {$limit} jobs from DLQ [{$dlqQueue}] on [{$connection}].");

        if (! $this->option('force') && ! $this->components->confirm('Continue?', true)) {
            $this->components->warn('Aborted.');

            return self::FAILURE;
        }

        $replayed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $job = Queue::connection($connection)->pop($dlqQueue);

            if ($job === null) {
                break;
            }

            $payload = $job->payload();

            unset($payload['cauce_dead_letter'], $payload['cauce_original_id'], $payload['cauce_failed_at'], $payload['cauce_exception']);
            $payload['attempts'] = 0;
            $payload['uuid'] = (string) (\Symfony\Component\Uid\Uuid::v4());

            $targetConnection = $payload['connection'] ?? $connection;
            $targetQueue = $payload['queue'] ?? 'default';

            unset($payload['connection'], $payload['queue']);

            Queue::connection($targetConnection)
                ->pushRaw(json_encode($payload, JSON_UNESCAPED_UNICODE), $targetQueue);

            $job->delete();
            $replayed++;
        }

        $this->components->info("Replayed {$replayed} job(s).");

        return self::SUCCESS;
    }
}
