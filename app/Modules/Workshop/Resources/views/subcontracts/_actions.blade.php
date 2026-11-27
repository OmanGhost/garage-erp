{{-- send / receive / cancel / print buttons for one outside work order --}}
<a href="{{ route('subcontracts.print', $o) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><i class="bi bi-printer"></i></a>
@can('subcontracts.update')
    @if($o->status === 'draft')
        <form method="post" action="{{ route('subcontracts.action', [$o, 'send']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-box-arrow-up-right"></i> {{ __('Send out') }}</button></form>
    @endif
    @if($o->status === 'sent_out')
        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#receive-{{ $o->id }}"><i class="bi bi-box-arrow-in-down-left"></i> {{ __('Receive back') }}</button>
        <div class="modal fade text-start" id="receive-{{ $o->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form method="post" action="{{ route('subcontracts.action', [$o, 'receive']) }}" class="modal-content">@csrf
            <div class="modal-header"><h5 class="modal-title">{{ __('Receive back') }} {{ $o->number }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">{{ __('Actual cost (before VAT)') }}</label><input type="number" step="0.001" min="0" name="actual_cost" class="form-control" value="{{ $o->quoted_cost }}" required></div>
                @if(tenant()->company()?->moduleEnabled('purchasing'))
                    <div class="row g-2">
                        <div class="col-7"><label class="form-label">{{ __('Supplier invoice no.') }}</label><input name="supplier_invoice_number" class="form-control" maxlength="60"></div>
                        <div class="col-5"><label class="form-label">{{ __('VAT %') }}</label><input type="number" step="0.01" min="0" max="100" name="tax_rate" class="form-control" value="{{ tenant()->company()?->vat_rate ?? 5 }}"></div>
                    </div>
                    <div class="form-text">{{ __('A draft supplier invoice is prepared on the subcontracted work cost account. Review and post it from purchasing.') }}</div>
                @endif
            </div>
            <div class="modal-footer"><button class="btn btn-success">{{ __('Receive back') }}</button></div>
        </form></div></div>
    @endif
    @if(in_array($o->status, ['draft', 'sent_out'], true))
        <form method="post" action="{{ route('subcontracts.action', [$o, 'cancel']) }}" class="d-inline" data-confirm="{{ __('Cancel this outside work order?') }}">@csrf<button class="btn btn-sm btn-outline-danger" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}"><i class="bi bi-x-lg"></i></button></form>
    @endif
@endcan
