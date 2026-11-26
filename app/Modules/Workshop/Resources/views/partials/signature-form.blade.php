{{-- Drawn signature sent as a PNG file (hosting firewalls refuse long base64 fields). $action, $who, $askName, $name --}}
@php $sid = 'sig'.\Illuminate\Support\Str::random(6); @endphp
<form method="post" action="{{ $action }}" enctype="multipart/form-data" class="sig-form" data-sig="{{ $sid }}">@csrf
    <input type="hidden" name="who" value="{{ $who ?? 'customer' }}">
    <canvas id="{{ $sid }}" class="signature-pad" style="height:130px" aria-label="{{ __('Signature') }}"></canvas>
    <input type="file" name="signature_file" accept="image/png" class="d-none" tabindex="-1" aria-hidden="true">
    @if($askName ?? false)<input name="name" class="form-control form-control-sm mt-1" value="{{ $name ?? '' }}" placeholder="{{ __('Name') }}" required aria-label="{{ __('Name') }}">@endif
    <div class="d-flex gap-1 mt-1">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-sig-clear>{{ __('Clear') }}</button>
        <button class="btn btn-sm btn-primary ms-auto"><i class="bi bi-check2"></i> {{ __('Save signature') }}</button>
    </div>
    <div class="small text-danger d-none" data-sig-empty>{{ __('Please sign first.') }}</div>
</form>
@once
<script src="{{ asset('vendor/signature/signature_pad.umd.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form.sig-form').forEach(function (form) {
        const canvas = document.getElementById(form.dataset.sig);
        if (!canvas || !window.SignaturePad) return;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio; canvas.height = canvas.offsetHeight * ratio; canvas.getContext('2d').scale(ratio, ratio);
        const pad = new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)' });
        form.querySelector('[data-sig-clear]').addEventListener('click', () => pad.clear());
        form.addEventListener('submit', function (e) {
            const empty = form.querySelector('[data-sig-empty]');
            if (pad.isEmpty() || !window.DataTransfer) { e.preventDefault(); empty.classList.remove('d-none'); return; }
            const data = pad.toDataURL('image/png'), bin = atob(data.slice(22)), bytes = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
            const dt = new DataTransfer(); dt.items.add(new File([bytes], 'signature.png', { type: 'image/png' }));
            form.querySelector('input[type=file][name=signature_file]').files = dt.files;
        });
    });
});
</script>
@endonce
