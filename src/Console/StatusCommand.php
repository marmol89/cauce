<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;

class StatusCommand extends Command
{
    protected $signature = 'cauce:status
                            {--connection= : Filter by connection}
                            {--queue= : Filter by queue}
                            {--hours=24 : Look-back window in hours}';

    protected $description = 'Show a CLI overview of queue status and metrics.';

    public function handle(JobRepository $jobs, MetricsRepository $metrics): int
    {
        $hours = (int) $this->option('hours');
        $from = CarbonImmutable::now()->subHours($hours);
        $to = CarbonImmutable::now();

        $connection = $this->option('connection') ?: '*';
        $queue = $this->option('queue') ?: '*';

        $this->components->info("Cauce — last $hours hours");
        $this->newLine();

        $this->components->twoColumnDetail(
            "<fg=cyan>Connection:</>  $connection",
            "<fg=cyan>Queue:</>       $queue",
        );

        $totals = $metrics->totals($connection, $queue, $from, $to);

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Processed', number_format($totals['processed'])],
                ['Failed', number_format($totals['failed'])],
                ['Success rate', $totals['success_rate'] . '%'],
                ['Avg runtime', $totals['runtime_avg_ms'] . ' ms'],
                ['Throughput', $totals['throughput_per_min'] . ' / min'],
            ],
        );

        $this->newLine();
        $this->components->info('By status:');
        $statuses = $jobs->countsByStatus($hours);
        if ($statuses->isEmpty()) {
            $this->line('  (no data)');
        } else {
            foreach ($statuses as $status => $count) {
                $this->line(sprintf('  %-12s %d', $status, $count));
            }
        }

        return self::SUCCESS;
    }
}
