<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="cauce-card">
        <h3 class="font-semibold text-slate-900 mb-4">By connection (24h)</h3>
        <ul class="divide-y divide-slate-100">
            @forelse($this->byConnection as $name => $total)
                <li class="py-2 flex items-center justify-between">
                    <span class="text-sm text-slate-700">{{ $name }}</span>
                    <span class="text-sm font-mono text-slate-700">{{ number_format($total) }}</span>
                </li>
            @empty
                <li class="py-2 text-sm text-slate-500">No data.</li>
            @endforelse
        </ul>
    </div>
    <div class="cauce-card">
        <h3 class="font-semibold text-slate-900 mb-4">By queue (24h)</h3>
        <ul class="divide-y divide-slate-100">
            @forelse($this->byQueue as $name => $total)
                <li class="py-2 flex items-center justify-between">
                    <span class="text-sm text-slate-700 font-mono">{{ $name }}</span>
                    <span class="text-sm font-mono text-slate-700">{{ number_format($total) }}</span>
                </li>
            @empty
                <li class="py-2 text-sm text-slate-500">No data.</li>
            @endforelse
        </ul>
    </div>
</div>
