@extends('layouts.app')
@section('title', __('Backups'))
@section('content')
<x-page-header :title="__('Backups')" icon="bi-hdd-stack" :breadcrumbs="[__('Administration'), __('Backups')]" :subtitle="__('Automatic daily backups run from the scheduler. Restore with: php artisan erp:restore <file> (see docs/BACKUP_RESTORE.md).')">
    @can('backups.create')<form method="post" action="{{ route('backups.run') }}">@csrf <x-idem/><button class="btn btn-primary"><i class="bi bi-cloud-arrow-up"></i> {{ __('Back up now') }}</button></form>@endcan
</x-page-header>
<div class="card"><table class="table table-erp mb-0">
    <thead><tr><th>{{ __('File') }}</th><th>{{ __('Size') }}</th><th>{{ __('Date') }}</th><th></th></tr></thead>
    <tbody>@forelse($files as $f)<tr><td class="font-monospace">{{ $f['name'] }}</td><td>{{ number_format($f['size'] / 1048576, 2) }} MB</td><td>{{ date('Y-m-d H:i', $f['time']) }}</td>
        <td class="text-end"><a href="{{ route('backups.download', $f['name']) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a></td></tr>
    @empty<tr><td colspan="4" class="text-body-secondary">{{ __('No backups yet.') }}</td></tr>@endforelse</tbody>
</table></div>
@endsection
