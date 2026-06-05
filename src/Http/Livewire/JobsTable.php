<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Marmol89\Cauce\Contracts\JobRepository;

class JobsTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $connection = '';

    #[Url]
    public string $queue = '';

    #[Url]
    public string $status = '';

    public int $perPage;

    public function mount(): void
    {
        $this->perPage = (int) config('cauce.dashboard.rows_per_page', 25);
    }

    public function render()
    {
        return view('cauce::livewire.jobs-table');
    }

    #[Computed]
    public function jobs()
    {
        return app(JobRepository::class)->paginate(
            array_filter([
                'name' => $this->search,
                'connection' => $this->connection,
                'queue' => $this->queue,
                'status' => $this->status,
            ], fn ($v) => $v !== ''),
            $this->perPage,
        );
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedConnection(): void
    {
        $this->resetPage();
    }

    public function updatedQueue(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }
}
