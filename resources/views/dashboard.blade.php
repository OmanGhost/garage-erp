@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
<x-page-header :title="__('Dashboard')" icon="bi-speedometer2" :subtitle="__('Welcome back, :name', ['name' => auth()->user()->name]).' · '.now()->translatedFormat('l, d F Y')">
    <div class="btn-group">
        @foreach(['day' => __('Today'), 'month' => __('This month'), 'year' => __('This year')] as $k => $l)
            <a href="{{ route('dashboard', ['period' => $k]) }}" class="btn btn-outline-secondary {{ $period === $k ? 'active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#widgetsModal"><i class="bi bi-sliders"></i> {{ __('Customize') }}</button>
</x-page-header>

@if(in_array('finance', $enabled) && $canFinance)
<div class="row g-3 mb-3">
    @foreach([
        ['revenue', __('Revenue'), 'bi-graph-up-arrow', 'soft-success'],
        ['expenses', __('Expenses & costs'), 'bi-graph-down-arrow', 'soft-danger'],
        ['net_profit', __('Net profit'), 'bi-cash-coin', 'soft-primary'],
        ['parts_sales', __('Spare parts sales'), 'bi-gear', 'soft-info'],
        ['inventory_value', __('Inventory value'), 'bi-boxes', 'soft-secondary'],
        ['receivables', __('Customer receivables'), 'bi-person-down', 'soft-warning'],
        ['payables', __('Supplier payables'), 'bi-truck', 'soft-warning'],
    ] as [$k, $label, $icon, $cls])
        <div class="col-6 col-md-4 col-xl">
            <div class="card kpi h-100"><div class="card-body d-flex gap-3 align-items-center">
                <span class="kpi-icon {{ $cls }}"><i class="bi {{ $icon }}"></i></span>
                <div class="min-w-0"><div class="kpi-label text-truncate">{{ $label }}</div><div class="kpi-value text-truncate">{{ money($d[$k] ?? 0, false) }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>
@endif

@if(in_array('workshop', $enabled))
<div class="row g-3 mb-3">
    @foreach([
        ['in_workshop', __('Vehicles in workshop'), 'bi-car-front', 'soft-primary', route('crud.index', ['job-cards', 'open' => 1])],
        ['in_progress', __('Under repair'), 'bi-wrench', 'soft-info', route('crud.index', ['job-cards', 'f_status' => 'in_progress'])],
        ['awaiting_approval', __('Awaiting approval'), 'bi-hourglass-split', 'soft-warning', route('workshop.board')],
        ['awaiting_parts', __('Awaiting spare parts'), 'bi-box-seam', 'soft-danger', route('crud.index', ['job-cards', 'f_status' => 'awaiting_spare_parts'])],
        ['ready', __('Ready for delivery'), 'bi-check2-circle', 'soft-success', route('crud.index', ['job-cards', 'f_status' => 'ready_for_delivery'])],
        ['opened_period', __('Opened (period)'), 'bi-folder-plus', 'soft-secondary', route('crud.index', 'job-cards')],
        ['closed_period', __('Closed (period)'), 'bi-folder-check', 'soft-secondary', route('crud.index', 'job-cards')],
    ] as [$k, $label, $icon, $cls, $url])
        <div class="col-6 col-md-4 col-xl">
            <a href="{{ $url }}" class="card kpi h-100 text-decoration-none"><div class="card-body d-flex gap-3 align-items-center">
                <span class="kpi-icon {{ $cls }}"><i class="bi {{ $icon }}"></i></span>
                <div><div class="kpi-label">{{ $label }}</div><div class="kpi-value text-body">{{ $d[$k] ?? 0 }}</div></div>
            </div></a>
        </div>
    @endforeach
</div>
@endif

<div class="row g-3">
    @if(in_array('revenue_chart', $enabled) && $canFinance)
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <span><i class="bi bi-bar-chart me-1"></i>{{ __('Revenue') }}</span>
                <div class="btn-group btn-group-sm ms-auto" role="group">
                    <button class="btn btn-outline-secondary active" data-chart-mode="daily">{{ __('Daily') }}</button>
                    <button class="btn btn-outline-secondary" data-chart-mode="monthly">{{ __('Monthly') }}</button>
                </div>
            </div>
            <div class="card-body"><div class="chart-box"><canvas id="revenueChart"></canvas></div></div>
        </div>
    </div>
    @endif
    @if(in_array('status_chart', $enabled))
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-1"></i>{{ __('Job cards by status') }}</div>
            <div class="card-body"><div class="chart-box"><canvas id="statusChart"></canvas></div></div>
        </div>
    </div>
    @endif
    @if(in_array('alerts', $enabled))
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bell me-1"></i>{{ __('Alerts') }}</div>
            <div class="list-group list-group-flush">
                @forelse($d['alerts'] as [$type, $icon, $text, $url])
                    <a href="{{ $url }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center"><span class="kpi-icon soft-{{ $type }}" style="width:34px;height:34px;font-size:1rem"><i class="bi {{ $icon }}"></i></span>{{ $text }}</a>
                @empty
                    <div class="empty-state"><i class="bi bi-emoji-smile"></i>{{ __('All clear. No alerts.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
    @if(in_array('technicians', $enabled))
    <div class="col-xl-4 col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-person-gear me-1"></i>{{ __('Technician performance') }}</div>
            <div class="table-responsive"><table class="table table-sm table-erp mb-0">
                <thead><tr><th>{{ __('Technician') }}</th><th>{{ __('Tasks') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Efficiency') }}</th></tr></thead>
                <tbody>
                @forelse($d['technicians'] as $t)
                    <tr><td>{{ $t->name }}</td><td>{{ $t->tasks }}</td><td>{{ round($t->minutes / 60, 1) }}</td>
                        <td>@if($t->minutes > 0)<span class="badge text-bg-{{ $t->est / $t->minutes >= 1 ? 'success' : 'warning' }}">{{ round($t->est * 100 / $t->minutes) }}%</span>@endif</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No data') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    @endif
    @if(in_array('departments', $enabled))
    <div class="col-xl-4 col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-diagram-3 me-1"></i>{{ __('Department performance') }}</div>
            <div class="table-responsive"><table class="table table-sm table-erp mb-0">
                <thead><tr><th>{{ __('Department') }}</th><th>{{ __('Jobs') }}</th><th class="text-end">{{ __('Revenue') }}</th></tr></thead>
                <tbody>
                @forelse($d['departments'] as $dep)
                    <tr><td>{{ app()->getLocale() === 'en' && $dep->name_en ? $dep->name_en : $dep->name_ar }}</td><td>{{ $dep->jobs }}</td><td class="text-end font-monospace">{{ money($dep->revenue, false) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No data') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    @endif
    @if(in_array('low_stock', $enabled))
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex"><span><i class="bi bi-exclamation-triangle me-1 text-warning"></i>{{ __('Low stock parts') }}</span><a href="{{ route('inventory.reorder') }}" class="ms-auto small">{{ __('Reorder suggestions') }}</a></div>
            <div class="table-responsive"><table class="table table-sm table-erp mb-0">
                <thead><tr><th>SKU</th><th>{{ __('Part') }}</th><th>{{ __('On hand') }}</th><th>{{ __('Minimum') }}</th></tr></thead>
                <tbody>
                @forelse($d['low_stock'] as $p)
                    <tr><td><a href="{{ route('parts.show', $p->id) }}">{{ $p->sku }}</a></td><td>{{ app()->getLocale() === 'en' && $p->name_en ? $p->name_en : $p->name_ar }}</td><td class="text-danger fw-bold">{{ qty($p->on_hand) }}</td><td>{{ qty($p->min_stock) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No low stock items') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    @endif
    @if(in_array('recent_jobs', $enabled))
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-clipboard-check me-1"></i>{{ __('Recent job cards') }}</div>
            <div class="list-group list-group-flush">
                @forelse($d['recent_jobs'] as $j)
                    <a href="{{ route('job-cards.show', $j) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        <span class="plate-badge small">{{ $j->vehicle?->plate() }}</span>
                        <span class="text-truncate">{{ $j->number }} · {{ $j->customer?->name }}</span>
                        <span class="ms-auto">{!! status_badge($j->status) !!}</span>
                    </a>
                @empty
                    <div class="empty-state"><i class="bi bi-inbox"></i>{{ __('No job cards yet') }}</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>

<div class="modal fade" id="widgetsModal" tabindex="-1">
    <div class="modal-dialog"><form method="post" action="{{ route('dashboard.widgets') }}" class="modal-content">@csrf
        <div class="modal-header"><h5 class="modal-title">{{ __('Customize dashboard') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            @foreach($widgets as $k => $label)
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="widgets[]" value="{{ $k }}" id="w_{{ $k }}" @checked(in_array($k, $enabled))><label class="form-check-label" for="w_{{ $k }}">{{ __($label) }}</label></div>
            @endforeach
        </div>
        <div class="modal-footer"><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form></div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script>
(function () {
    const css = getComputedStyle(document.documentElement);
    const brand = css.getPropertyValue('--brand').trim() || '#1f6feb';
    Chart.defaults.font.family = css.getPropertyValue('--font');
    Chart.defaults.color = getComputedStyle(document.body).color;
    const daily = @json($d['daily']), monthly = @json($d['monthly']);
    const el = document.getElementById('revenueChart');
    if (el) {
        const chart = new Chart(el, {
            type: 'bar',
            data: { labels: Object.keys(daily).map(d => d.slice(5)), datasets: [{ label: @json(__('Revenue')), data: Object.values(daily), backgroundColor: brand + 'cc', borderRadius: 6 }] },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
        document.querySelectorAll('[data-chart-mode]').forEach(b => b.addEventListener('click', () => {
            document.querySelectorAll('[data-chart-mode]').forEach(x => x.classList.remove('active')); b.classList.add('active');
            const src = b.dataset.chartMode === 'monthly' ? monthly : daily;
            chart.data.labels = Object.keys(src).map(d => b.dataset.chartMode === 'monthly' ? d : d.slice(5));
            chart.data.datasets[0].data = Object.values(src); chart.config.type = b.dataset.chartMode === 'monthly' ? 'line' : 'bar'; chart.update();
        }));
    }
    const sc = document.getElementById('statusChart');
    if (sc) {
        const counts = @json($d['status_counts']);
        const colors = @json(\App\Modules\Workshop\Services\JobCardWorkflow::COLORS);
        const labels = @json(collect(\App\Modules\Workshop\Models\JobCard::STATUSES)->mapWithKeys(fn($s) => [$s => status_label($s)]));
        const keys = Object.keys(counts).filter(k => !['completed', 'cancelled'].includes(k));
        new Chart(sc, { type: 'doughnut', data: { labels: keys.map(k => labels[k] || k), datasets: [{ data: keys.map(k => counts[k]), backgroundColor: keys.map(k => colors[k] || '#999') }] },
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, cutout: '62%' } });
    }
})();
</script>
@endpush
