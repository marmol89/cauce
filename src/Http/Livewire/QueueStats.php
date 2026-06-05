<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Marmol89\Cauce\Contracts\JobRepository;

class QueueStats extends Component
{
    public int $hours;

    public function mount(): void
    {
        $this->hours = (int) config('cauce.dashboard.refresh_hours', 24);
        $this->hours = max(1, min(168, $this->hours));
    }

    public function render()
    {
        return view('cauce::livewire.queue-stats');
    }

    #[Computed]
    public function byConnection()
    {
        return app(JobRepository::class)->countsByConnection($this->hours);
    }

    #[Computed]
    public function byQueue()
    {
        return app(JobRepository::class)->countsByQueue($this->hours);
    }
}
