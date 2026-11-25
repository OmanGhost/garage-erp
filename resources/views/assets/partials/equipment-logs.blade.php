<div class="card mb-3">
    <div class="card-header d-flex">{{ __('Maintenance, breakdown & calibration log') }}<a href="{{ route('crud.create', 'equipment-logs') }}" class="ms-auto small">+ {{ __('Add entry') }}</a></div>
    <div class="table-responsive"><table class="table table-sm table-erp mb-0">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Cost') }}</th><th>{{ __('Downtime') }}</th></tr></thead>
        <tbody>
        @forelse($equipment->logs()->latest('date')->get() as $l)
            <tr><td>{{ $l->date->format('Y-m-d') }}</td><td>{{ status_label($l->type) }}</td><td>{{ $l->description }}</td><td class="text-end font-monospace">{{ money($l->cost, false) }}</td><td>{{ (float) $l->downtime_hours }} h</td></tr>
        @empty<tr><td colspan="5" class="text-body-secondary">—</td></tr>@endforelse
        </tbody>
        <tfoot><tr class="fw-bold"><td colspan="3">{{ __('Total operating cost') }}</td><td class="text-end font-monospace">{{ money($equipment->logs()->sum('cost')) }}</td><td></td></tr></tfoot>
    </table></div>
</div>
