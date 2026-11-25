@extends('layouts.app')
@section('title', __('Manual journal entry'))
@section('content')
<x-page-header :title="__('Manual journal entry')" icon="bi-journal-plus" :breadcrumbs="[__('Journal Entries') => route('crud.index', 'journal-entries'), __('New')]"/>
<form method="post" action="{{ route('journal.store') }}" class="card" x-data="{ lines: [{a:'', d:'', c:'', cc:'', t:''}, {a:'', d:'', c:'', cc:'', t:''}],
        get dr() { return this.lines.reduce((s, l) => s + (Number(l.d) || 0), 0) }, get cr() { return this.lines.reduce((s, l) => s + (Number(l.c) || 0), 0) } }">@csrf <x-idem/>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label">{{ __('Date') }}</label><input type="date" name="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
        <div class="col-md-9"><label class="form-label">{{ __('Description') }}</label><input name="description" class="form-control" required></div>
        <div class="col-12"><table class="table table-sm">
            <thead><tr><th>{{ __('Account') }}</th><th style="width:130px">{{ __('Debit') }}</th><th style="width:130px">{{ __('Credit') }}</th><th>{{ __('Cost center') }}</th><th>{{ __('Line description') }}</th><th></th></tr></thead>
            <tbody><template x-for="(l, i) in lines" :key="i"><tr>
                <td><select class="form-select form-select-sm" :name="`lines[${i}][account_id]`" x-model="l.a"><option value="">—</option>@foreach($accounts as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></td>
                <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" :name="`lines[${i}][debit]`" x-model="l.d"></td>
                <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" :name="`lines[${i}][credit]`" x-model="l.c"></td>
                <td><select class="form-select form-select-sm" :name="`lines[${i}][cost_center_id]`" x-model="l.cc"><option value="">—</option>@foreach($costCenters as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></td>
                <td><input class="form-control form-control-sm" :name="`lines[${i}][description]`" x-model="l.t"></td>
                <td><button type="button" class="btn btn-sm btn-link text-danger" @click="lines.splice(i,1)"><i class="bi bi-x"></i></button></td>
            </tr></template></tbody>
            <tfoot><tr class="fw-bold"><td><button type="button" class="btn btn-sm btn-outline-primary" @click="lines.push({a:'', d:'', c:'', cc:'', t:''})">+ {{ __('Line') }}</button></td>
                <td class="font-monospace" x-text="erpFmt(dr)"></td><td class="font-monospace" x-text="erpFmt(cr)"></td>
                <td colspan="3"><span class="badge" :class="Math.abs(dr - cr) < 0.0005 && dr > 0 ? 'text-bg-success' : 'text-bg-danger'" x-text="Math.abs(dr - cr) < 0.0005 && dr > 0 ? '{{ __('Balanced') }}' : '{{ __('Not balanced') }}'"></span></td></tr></tfoot>
        </table></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary" :disabled="Math.abs(dr - cr) >= 0.0005 || dr <= 0">{{ __('Post entry') }}</button></div>
</form>
@endsection
