<div wire:init="refresh" class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="cauce-card">
            <p class="text-sm text-slate-500">Processed (24h)</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($this->stats['totals']['processed'] ?? 0) }}</p>
        </div>
        <div class="cauce-card">
            <p class="text-sm text-slate-500">Failed (24h)</p>
            <p class="mt-2 text-3xl font-semibold text-rose-600">{{ number_format($this->stats['totals']['failed'] ?? 0) }}</p>
        </div>
        <div class="cauce-card">
            <p class="text-sm text-slate-500">Success rate</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ number_format($this->stats['totals']['success_rate'] ?? 0, 1) }}%</p>
        </div>
        <div class="cauce-card">
            <p class="text-sm text-slate-500">Avg throughput</p>
            <p class="mt-2 text-3xl font-semibold text-indigo-600">{{ number_format($this->stats['totals']['throughput_per_min'] ?? 0, 1) }}<span class="text-base text-slate-400">/min</span></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="cauce-card">
            <h3 class="font-semibold text-slate-900 mb-4">By status</h3>
            <ul class="divide-y divide-slate-100">
                @forelse($this->stats['by_status'] as $status => $total)
                    <li class="py-2 flex items-center justify-between">
                        <span class="cauce-badge {{ match($status) {
                            'completed' => 'bg-emerald-100 text-emerald-800',
                            'failed' => 'bg-rose-100 text-rose-800',
                            'processing' => 'bg-amber-100 text-amber-800',
                            'retrying' => 'bg-orange-100 text-orange-800',
                            default => 'bg-slate-100 text-slate-800',
                        } }}">{{ ucfirst($status) }}</span>
                        <span class="text-sm font-mono text-slate-700">{{ number_format($total) }}</span>
                    </li>
                @empty
                    <li class="py-2 text-sm text-slate-500">No jobs in window.</li>
                @endforelse
            </ul>
        </div>

        <div class="cauce-card">
            <h3 class="font-semibold text-slate-900 mb-4">By connection</h3>
            <ul class="divide-y divide-slate-100">
                @forelse($this->stats['by_connection'] as $connection => $total)
                    <li class="py-2 flex items-center justify-between">
                        <span class="text-sm text-slate-700">{{ $connection }}</span>
                        <span class="text-sm font-mono text-slate-700">{{ number_format($total) }}</span>
                    </li>
                @empty
                    <li class="py-2 text-sm text-slate-500">No jobs in window.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
