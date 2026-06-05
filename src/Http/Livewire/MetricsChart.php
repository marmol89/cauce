<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Livewire;

use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Marmol89\Cauce\Contracts\MetricsRepository;

class MetricsChart extends Component
{
    #[Url]
    public string $connection = '*';

    #[Url]
    public string $queue = '*';

    #[Url]
    public int $hours = 6;

    public function render()
    {
        return view('cauce::livewire.metrics-chart');
    }

    #[Computed]
    public function series(): array
    {
        $from = CarbonImmutable::now()->subHours($this->hours);
        $to = CarbonImmutable::now();
        $repo = app(MetricsRepository::class);

        return [
            'processed' => $repo->series($this->connection, $this->queue, 'processed', $from, $to),
            'failed' => $repo->series($this->connection, $this->queue, 'failed', $from, $to),
            'throughput' => $repo->series($this->connection, $this->queue, 'throughput', $from, $to),
            'runtime_avg_ms' => $repo->series($this->connection, $this->queue, 'runtime_avg_ms', $from, $to),
        ];
    }
}
