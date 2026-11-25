<!DOCTYPE html>
@php $company = $company ?? tenant()->company(); $rtl = is_rtl(); @endphp
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        @page { size: {{ $paper ?? 'A4' }}; margin: {{ ($paper ?? 'A4') === '80mm' ? '2mm' : '10mm' }}; }
        * { box-sizing: border-box; }
        body { font-family: dejavusans, "Segoe UI", Tahoma, Arial, sans-serif; color: #111; font-size: 12px; margin: 0; background: #e9ecef; }
        .paper { background: #fff; max-width: {{ ($paper ?? 'A4') === '80mm' ? '80mm' : '210mm' }}; margin: 10px auto; padding: {{ ($paper ?? 'A4') === '80mm' ? '3mm' : '12mm' }}; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; }
        .muted { color: #666; font-size: 11px; }
        .doc-title { text-align: center; font-size: 17px; font-weight: bold; margin: 10px 0; padding: 6px; border: 2px solid #222; border-radius: 6px; }
        .grid th, .grid td { border: 1px solid #bbb; padding: 5px 6px; text-align: {{ $rtl ? 'right' : 'left' }}; }
        .grid th { background: #f1f3f5; font-size: 11px; }
        .num { text-align: {{ $rtl ? 'left' : 'right' }} !important; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { font-size: 15px; font-weight: bold; border-top: 2px solid #222; }
        .box { border: 1px solid #ccc; border-radius: 6px; padding: 8px; }
        .sign { height: 70px; border-bottom: 1px dashed #999; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; background: #eee; font-size: 10px; }
        .toolbar { max-width: 210mm; margin: 10px auto 0; text-align: center; }
        .toolbar button, .toolbar a { padding: 6px 14px; margin: 2px; border: 1px solid #888; background: #fff; border-radius: 6px; cursor: pointer; text-decoration: none; color: #111; font-size: 13px; }
        .bilingual { font-size: 10px; color: #555; display: block; }
        @media print { body { background: #fff; } .paper { box-shadow: none; margin: 0; max-width: none; } .toolbar { display: none; } }
        @yield('styles')
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">🖨 {{ __('Print') }}</button>
    @hasSection('pdf-url')<a href="@yield('pdf-url')" target="_blank">PDF</a>@endif
    <button onclick="window.close()">✕ {{ __('Close') }}</button>
</div>
<div class="paper">
    @unless(($paper ?? 'A4') === '80mm')
    <table class="head">
        <tr>
            <td style="width:60%">
                <div class="company-name">{{ $company?->name_ar ?? $company?->name }}</div>
                @if($company?->name_en)<div style="font-weight:bold">{{ $company->name_en }}</div>@endif
                <div class="muted">{{ $company?->address }} {{ $company?->city }} · {{ $company?->phone }} · {{ $company?->email }}</div>
                <div class="muted">
                    @if($company?->cr_number)س.ت / C.R: {{ $company->cr_number }}@endif
                    @if($company?->vat_number) · الرقم الضريبي / VATIN: {{ $company->vat_number }}@endif
                </div>
            </td>
            <td style="text-align:{{ $rtl ? 'left' : 'right' }}">
                @if($company?->logoUrl())<img src="{{ $company->logoUrl() }}" style="max-height:70px;max-width:180px">@endif
            </td>
        </tr>
    </table>
    @endunless
    @yield('content')
</div>
</body>
</html>
