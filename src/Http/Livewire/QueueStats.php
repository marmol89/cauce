<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Marmol89\Cauce\Contracts\JobRepository;

class QueueStats extends Component
{
    public function render()
    {
        return view('cauce::livewire.queue-stats');
    }

    #[Computed]
    public function byConnection()
    {
        return app(JobRepository::class)->countsByConnection(24);
    }

    #[Computed]
    public function byQueue()
    {
        return app(JobRepository::class)->countsByQueue(24);
    }
}
