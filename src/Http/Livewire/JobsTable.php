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

    #[Url]
    public string $tag = '';

    public int $perPage;

    protected JobRepository $jobRepository;

    public function boot(JobRepository $jobs): void
    {
        $this->jobRepository = $jobs;
    }

    public function mount(): void
    {
        $this->perPage = max(5, min(100, (int) config('cauce.dashboard.rows_per_page', 25)));
    }

    public function updatedPerPage(): void
    {
        $this->perPage = max(5, min(100, $this->perPage));
    }

    public function render()
    {
        return view('cauce::livewire.jobs-table');
    }

    #[Computed]
    public function jobs()
    {
        return $this->jobRepository->paginate(
            array_filter([
                'name' => $this->search,
                'connection' => $this->connection,
                'queue' => $this->queue,
                'status' => $this->status,
                'tags' => $this->tag !== '' ? [$this->tag] : null,
            ], fn ($v) => $v !== ''),
            $this->perPage,
        );
    }

    #[Computed]
    public function connections()
    {
        return $this->jobRepository->distinctConnections();
    }

    #[Computed]
    public function queues()
    {
        return $this->jobRepository->distinctQueues();
    }

    #[Computed]
    public function availableTags()
    {
        return $this->jobRepository->distinctTags();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr($this->search, 0, 255);
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

    public function updatedTag(): void
    {
        $this->resetPage();
    }
}
