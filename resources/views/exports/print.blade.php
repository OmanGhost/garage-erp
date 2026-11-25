<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: dejavusans, "Segoe UI", Tahoma, Arial, sans-serif; font-size: 11px; color: #111; margin: 12px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 10px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: {{ is_rtl() ? 'right' : 'left' }}; vertical-align: top; }
        th { background: #f0f2f6; font-weight: bold; }
        tr:nth-child(even) td { background: #fafbfc; }
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; margin-bottom: 8px; padding-bottom: 6px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body @if(empty($pdf)) onload="window.print()" @endif>
@php $company = tenant()->company(); @endphp
<div class="head">
    <div>
        <h1>{{ $title }}</h1>
        <div class="meta">{{ $company?->displayName() }} @if($company?->vat_number) · {{ __('VAT No.') }} {{ $company->vat_number }} @endif · {{ now()->format('Y-m-d H:i') }} · {{ auth()->user()?->name }}</div>
        @if(!empty($meta['filters']))<div class="meta">@foreach($meta['filters'] as $k => $v) {{ __(ucfirst($k)) }}: {{ $v }} &nbsp; @endforeach</div>@endif
    </div>
</div>
<table>
    <thead><tr>@foreach($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach($rows as $r)
        <tr>@foreach(array_values((array) $r) as $v)<td>{{ $v }}</td>@endforeach</tr>
    @endforeach
    </tbody>
    @if(!empty($meta['totals']))
        <tfoot><tr>@foreach($meta['totals'] as $t)<th>{{ $t }}</th>@endforeach</tr></tfoot>
    @endif
</table>
</body>
</html>
