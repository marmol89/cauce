<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Queue;
use Marmol89\Cauce\Contracts\JobRepository;

class RetryCommand extends Command
{
    protected $signature = 'cauce:retry
                            {id : ID of the failed job to retry}
                            {--queue= : Re-queue onto a specific queue}
                            {--connection= : Re-queue on a specific connection}';

    protected $description = 'Retry a failed job tracked by Cauce.';

    public function handle(JobRepository $jobs): int
    {
        $row = $jobs->find($this->argument('id'));

        if ($row === null) {
            $this->components->error("No Cauce job with id [{$this->argument('id')}].");

            return self::FAILURE;
        }

        $this->components->info("Re-queueing job:");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $row->id],
                ['Class', $row->name],
                ['Connection', $row->connection],
                ['Queue', $row->queue],
            ],
        );

        if (! $this->components->confirm('Dispatch this job again?', true)) {
            $this->components->warn('Aborted.');

            return self::FAILURE;
        }

        if (! $this->dispatchFromPayload($row)) {
            $this->components->error('Could not reconstruct the job from the stored payload.');

            return self::FAILURE;
        }

        $jobs->retry($row->id);

        $this->components->info('Job re-queued. Cauce record reset.');

        return self::SUCCESS;
    }

    protected function dispatchFromPayload(object $row): bool
    {
        $payload = is_string($row->payload) ? json_decode($row->payload, true) : (array) $row->payload;

        if (! is_array($payload) || empty($payload['data']['commandName'])) {
            return false;
        }

        $command = $payload['data']['commandName'];
        $arguments = $payload['data']['command'] ?? null;

        if (! class_exists($command)) {
            return false;
        }

        $connection = $this->option('connection') ?: $row->connection;
        $queue = $this->option('queue') ?: $row->queue;

        Queue::connection($connection)->push($arguments, '', $queue);

        return true;
    }
}
