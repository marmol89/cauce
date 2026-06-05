<div>
    <div class="cauce-card mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-slate-500">Connection</label>
            <input wire:model.live="connection" class="rounded border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500">Queue</label>
            <input wire:model.live="queue" class="rounded border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500">Window (hours)</label>
            <input type="number" min="1" max="48" wire:model.live="hours" class="rounded border-slate-200 text-sm w-24">
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="cauce-card">
            <h3 class="font-semibold text-slate-900 mb-2">Processed per minute</h3>
            <canvas id="cauce-chart-processed" wire:ignore height="160"></canvas>
        </div>
        <div class="cauce-card">
            <h3 class="font-semibold text-slate-900 mb-2">Failures per minute</h3>
            <canvas id="cauce-chart-failed" wire:ignore height="160"></canvas>
        </div>
        <div class="cauce-card">
            <h3 class="font-semibold text-slate-900 mb-2">Throughput</h3>
            <canvas id="cauce-chart-throughput" wire:ignore height="160"></canvas>
        </div>
        <div class="cauce-card">
            <h3 class="font-semibold text-slate-900 mb-2">Avg runtime (ms)</h3>
            <canvas id="cauce-chart-runtime" wire:ignore height="160"></canvas>
        </div>
    </div>

    @script
    <script>
        const series = @js($this->series);

        const labels = (key) => (series[key] || []).map(p => new Date(p.minute).toLocaleTimeString());
        const values = (key) => (series[key] || []).map(p => p.value);

        const render = (id, label, data, color) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (el._chart) el._chart.destroy();
            el._chart = new Chart(el, {
                type: 'line',
                data: {
                    labels: labels(label),
                    datasets: [{
                        label,
                        data: values(label),
                        borderColor: color,
                        backgroundColor: color + '33',
                        fill: true,
                        tension: 0.3,
                    }],
                },
                options: { responsive: true, plugins: { legend: { display: false } } },
            });
        };

        render('cauce-chart-processed', 'processed', null, '#10b981');
        render('cauce-chart-failed', 'failed', null, '#f43f5e');
        render('cauce-chart-throughput', 'throughput', null, '#6366f1');
        render('cauce-chart-runtime', 'runtime_avg_ms', null, '#f59e0b');

        Livewire.on('cauce:refresh', () => {
            render('cauce-chart-processed', 'processed', null, '#10b981');
            render('cauce-chart-failed', 'failed', null, '#f43f5e');
            render('cauce-chart-throughput', 'throughput', null, '#6366f1');
            render('cauce-chart-runtime', 'runtime_avg_ms', null, '#f59e0b');
        });
    </script>
    @endscript
</div>
