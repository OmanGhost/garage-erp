@extends('layouts.portal')
@section('title', __('Statement'))
@section('content')
<h5>{{ __('Account statement') }}</h5>
<form class="d-flex gap-2 mb-3"><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-primary">{{ __('Show') }}</button></form>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <tr class="table-light"><td colspan="3">{{ __('Opening balance') }}</td><td class="text-end font-monospace">{{ money($st['opening'], false) }}</td></tr>
    @foreach($st['rows'] as $r)<tr><td class="small">{{ $r->date }}</td><td class="small">{{ $r->number }}<div class="text-body-secondary">{{ $r->line_description ?: $r->description }}</div></td>
        <td class="text-end font-monospace small">{{ (float) $r->debit ? '+'.money($r->debit, false) : '-'.money($r->credit, false) }}</td><td class="text-end font-monospace small">{{ money($r->balance, false) }}</td></tr>@endforeach
    <tr class="fw-bold"><td colspan="3">{{ __('Closing balance') }}</td><td class="text-end font-monospace">{{ money($st['closing']) }}</td></tr>
</table></div></div>
@endsection
