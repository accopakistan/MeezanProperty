@props([
    'id',
    'title',
    'subtitle' => null,
    'type' => 'bar',
    'labels' => [],
    'datasets' => [],
    'height' => '260px',
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'card p-5 flex flex-col justify-between ' . $class]) }}>
    <div>
        <div class="flex items-start justify-between gap-2 mb-3">
            <div>
                <h3 class="text-sm font-bold text-ink uppercase tracking-wider">{{ $title }}</h3>
                @if ($subtitle)
                    <p class="text-[12px] text-muted mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            <span class="inline-flex items-center rounded-md bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700 ring-1 ring-brand-600/20">
                Verified Data
            </span>
        </div>
        <div class="relative w-full" style="height: {{ $height }};">
            <canvas id="{{ $id }}"></canvas>
        </div>
    </div>
</div>

@pushOnce('scripts')
<script src="{{ asset('chart.umd.min.js') }}"></script>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
window.MeezanCharts = window.MeezanCharts || {
    instances: {},
    init(id, config) {
        if (!window.Chart) return;
        const ctx = document.getElementById(id);
        if (!ctx) return;
        if (this.instances[id]) {
            this.instances[id].destroy();
        }
        
        // Brand palette defaults
        Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
        Chart.defaults.color = '#64748B';
        Chart.defaults.plugins.tooltip.backgroundColor = '#21133A';
        Chart.defaults.plugins.tooltip.titleColor = '#D6A13D';
        Chart.defaults.plugins.tooltip.bodyColor = '#FFFFFF';
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 6;
        
        this.instances[id] = new Chart(ctx, config);
    }
};
</script>
@endPushOnce

@push('scripts')
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
document.addEventListener('DOMContentLoaded', function() {
    @php
        $chartType = $type === 'horizontalBar' ? 'bar' : $type;
        $indexAxis = $type === 'horizontalBar' ? 'y' : 'x';
    @endphp
    
    window.MeezanCharts.init('{{ $id }}', {
        type: '{{ $chartType }}',
        data: {
            labels: @json($labels),
            datasets: @json($datasets)
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: '{{ $indexAxis }}',
            plugins: {
                legend: {
                    display: {{ in_array($type, ['doughnut', 'pie']) || count($datasets) > 1 ? 'true' : 'false' }},
                    position: '{{ in_array($type, ['doughnut', 'pie']) ? 'right' : 'top' }}',
                    labels: {
                        boxWidth: 12,
                        padding: 12,
                        font: { size: 11, weight: '600' }
                    }
                }
            },
            scales: {!! in_array($type, ['doughnut', 'pie']) ? '{}' : '{
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                },
                y: {
                    grid: { color: "#F1F5F9" },
                    ticks: { font: { size: 11 } }
                }
            }' !!}
        }
    });
});
</script>
@endpush
