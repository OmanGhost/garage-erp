{{--
    Server messages are shown as toast notifications by public/js/ui.js.
    Optional session keys: "<type>_title" (toast title) and "print_voucher" (action button on the success toast).
--}}
@php
    $flash = [];
    foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $key => $type) {
        if (session($key)) {
            $item = ['type' => $type, 'message' => (string) session($key), 'title' => session($key.'_title')];
            if ($key === 'success' && session('print_voucher')) {
                $item['action'] = ['label' => session('print_label') ?: __('Print voucher'), 'url' => session('print_voucher'), 'blank' => true, 'icon' => 'bi-printer'];
                $item['timeout'] = 15000;
            }
            // messages that carry a one-time secret (temporary passwords) stay until closed
            if (preg_match('/password|كلمة المرور/iu', $item['message'])) {
                $item['timeout'] = 0;
            }
            $flash[] = $item;
        }
    }
    if ($errors->any()) {
        $flash[] = ['type' => 'danger', 'title' => __('Please correct the following errors:'), 'message' => $errors->all()];
    }
@endphp
@if($flash)
    <script type="application/json" id="erp-flash">@json($flash)</script>
    <noscript>
        @foreach($flash as $f)
            <div class="alert alert-{{ $f['type'] }} d-print-none" role="alert">{{ is_array($f['message']) ? implode(' · ', $f['message']) : $f['message'] }}</div>
        @endforeach
    </noscript>
@endif
@if($errors->any())
    {{-- errors also stay visible on the page, next to the form --}}
    <div class="alert alert-danger alert-dismissible fade show d-print-none ui-inline-errors" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Please correct the following errors:') }}</div>
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
