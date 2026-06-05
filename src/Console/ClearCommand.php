<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;

class ClearCommand extends Command
{
    protected $signature = 'cauce:clear
                            {--jobs : Clear job records}
                            {--metrics : Clear metrics records}
                            {--breakers : Clear circuit breaker state}
                            {--all : Clear all Cauce data}
                            {--force : Skip confirmation}';

    protected $description = 'Wipe stored Cauce data.';

    public function handle(ConnectionResolverInterface $resolver): int
    {
        $connection = $this->resolveConnection($resolver);

        $clearJobs = $this->option('all') || $this->option('jobs');
        $clearMetrics = $this->option('all') || $this->option('metrics');
        $clearBreakers = $this->option('all') || $this->option('breakers');

        if (! ($clearJobs || $clearMetrics || $clearBreakers)) {
            $this->components->warn('Nothing to do. Use --jobs, --metrics, --breakers, or --all.');

            return self::FAILURE;
        }

        $this->components->warn('This action is irreversible.');
        if (! $this->option('force') && ! $this->components->confirm('Continue?', false)) {
            $this->components->info('Aborted.');

            return self::FAILURE;
        }

        if ($clearJobs) {
            $count = $connection->table('cauce_jobs')->delete();
            $this->components->twoColumnDetail('<fg=green>Cleared cauce_jobs:</>', "  $count rows");
        }

        if ($clearMetrics) {
            $count = $connection->table('cauce_metrics')->delete();
            $this->components->twoColumnDetail('<fg=green>Cleared cauce_metrics:</>', "  $count rows");
        }

        if ($clearBreakers) {
            $count = $connection->table('cauce_circuit_breakers')->delete();
            $this->components->twoColumnDetail('<fg=green>Cleared cauce_circuit_breakers:</>', "  $count rows");
        }

        $this->components->info('Clear complete.');

        return self::SUCCESS;
    }

    protected function resolveConnection(ConnectionResolverInterface $resolver): ConnectionInterface
    {
        $name = config('cauce.storage.database.connection');

        return $resolver->connection($name);
    }
}
