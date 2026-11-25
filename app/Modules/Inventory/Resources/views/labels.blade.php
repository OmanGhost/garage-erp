<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><title>{{ __('Labels') }}</title>
<style>
    @page { size: 50mm 30mm; margin: 0; }
    body { margin: 0; font-family: Arial, Tahoma, sans-serif; }
    .label { width: 50mm; height: 30mm; padding: 1.5mm; box-sizing: border-box; page-break-after: always; text-align: center; overflow: hidden; }
    .name { font-size: 8pt; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .meta { font-size: 7pt; }
    .price { font-size: 10pt; font-weight: bold; }
    img { max-width: 46mm; height: 11mm; }
    @media screen { body { background: #ddd; } .label { background: #fff; margin: 6px auto; box-shadow: 0 1px 3px rgba(0,0,0,.2); } }
</style></head>
<body onload="window.print()">
@foreach($parts as $p)
    @for($i = 0; $i < $copies; $i++)
        <div class="label">
            <div class="name">{{ $p->displayName() }}</div>
            <img src="{{ \App\Support\Codes::barcode($p->barcode ?: $p->sku, 1, 30) }}">
            <div class="meta">{{ $p->barcode ?: $p->sku }} @if($p->oem_number)· {{ $p->oem_number }}@endif @if($p->condition_grade)· {{ $p->condition_grade }}@endif</div>
            <div class="price">{{ money($p->sale_price) }}</div>
        </div>
    @endfor
@endforeach
</body></html>
