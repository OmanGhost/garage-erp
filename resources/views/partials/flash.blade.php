@foreach(['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $key => $type)
    @if(session($key))
        <div class="alert alert-{{ $type }} alert-dismissible fade show d-print-none" role="alert">
            <i class="bi {{ ['success' => 'bi-check-circle', 'danger' => 'bi-exclamation-triangle', 'warning' => 'bi-exclamation-circle', 'info' => 'bi-info-circle'][$type] }} me-1"></i>
            {{ session($key) }}
            @if($key === 'success' && session('print_voucher'))<a href="{{ session('print_voucher') }}" target="_blank" class="btn btn-sm btn-success ms-2"><i class="bi bi-printer"></i> {{ __('Print voucher') }}</a>@endif
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif
@endforeach
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show d-print-none" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Please correct the following errors:') }}</div>
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
