@php
    $rtl = is_rtl();
    $st = $inv->stage();
    $c = $inv->company;
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    $issuer = $s['billing_name'] ?: config('app.name');
    $rate = rtrim(rtrim((string) $inv->tax_rate, '0'), '.') ?: '0';
    $stamp = ['paid' => ['مدفوعة / PAID', '#198754'], 'cancelled' => ['ملغاة / CANCELLED', '#6c757d'], 'overdue' => ['متأخرة / OVERDUE', '#dc3545']][$st] ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $inv->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: dejavusans, "Segoe UI", Tahoma, Arial, sans-serif; color: #111; font-size: 12px; margin: 0; background: #e9ecef; }
        .paper { position: relative; background: #fff; max-width: 210mm; margin: 10px auto; padding: 12mm; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .issuer { font-size: 18px; font-weight: bold; }
        .muted { color: #666; font-size: 11px; }
        .title { text-align: center; font-size: 17px; font-weight: bold; margin: 12px 0; padding: 6px; border: 2px solid #222; border-radius: 6px; }
        .box { border: 1px solid #ccc; border-radius: 6px; padding: 8px; }
        .lbl { color: #555; font-size: 10px; }
        .grid th, .grid td { border: 1px solid #bbb; padding: 6px; text-align: {{ $start }}; }
        .grid th { background: #f1f3f5; font-size: 11px; }
        .num { text-align: {{ $end }} !important; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { font-size: 15px; font-weight: bold; border-top: 2px solid #222; }
        .stamp { position: absolute; top: 46mm; {{ $end }}: 16mm; transform: rotate(-12deg); border: 3px solid; border-radius: 8px; padding: 4px 12px; font-size: 18px; font-weight: bold; opacity: .8; }
        .toolbar { max-width: 210mm; margin: 10px auto 0; text-align: center; }
        .toolbar button, .toolbar a { padding: 6px 14px; margin: 2px; border: 1px solid #888; background: #fff; border-radius: 6px; cursor: pointer; text-decoration: none; color: #111; font-size: 13px; }
        @media print { body { background: #fff; } .paper { box-shadow: none; margin: 0; max-width: none; } .toolbar { display: none; } }
        @media (max-width: 600px) { .paper { padding: 5mm; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">🖨 {{ __('Print') }}</button>
    <a href="{{ request()->routeIs('admin.*') ? route('admin.billing.print', [$inv, 'pdf']) : route('billing.invoice', [$inv->id, 'pdf']) }}" target="_blank">PDF</a>
    <button onclick="window.close()">✕ {{ __('Close') }}</button>
</div>
<div class="paper">
    @if($stamp)<div class="stamp" style="color:{{ $stamp[1] }};border-color:{{ $stamp[1] }}">{{ $stamp[0] }}</div>@endif
    <table>
        <tr>
            <td style="width:60%">
                <div class="issuer">{{ $issuer }}</div>
                @if($s['billing_address'])<div class="muted">{{ $s['billing_address'] }}</div>@endif
                <div class="muted">
                    @if($s['billing_phone'])<bdi dir="ltr">{{ $s['billing_phone'] }}</bdi>@endif
                    @if($s['billing_email']) · <bdi dir="ltr">{{ $s['billing_email'] }}</bdi>@endif
                </div>
                <div class="muted">
                    @if($s['billing_cr_number'])س.ت / C.R: {{ $s['billing_cr_number'] }}@endif
                    @if($s['billing_vat_number']) · الرقم الضريبي / VATIN: {{ $s['billing_vat_number'] }}@endif
                </div>
            </td>
            <td style="text-align:{{ $end }}">
                <div class="lbl">رقم الفاتورة / Invoice No.</div>
                <div style="font-size:15px;font-weight:bold;font-family:monospace">{{ $inv->number }}</div>
            </td>
        </tr>
    </table>

    <div class="title">{{ $s['billing_vat_number'] ? 'فاتورة ضريبية / Tax Invoice' : 'فاتورة / Invoice' }}</div>

    <table>
        <tr>
            <td style="width:55%;padding-{{ $end }}:6px">
                <div class="box">
                    <div class="lbl">العميل / Bill to</div>
                    <div style="font-weight:bold;font-size:13px">{{ $c?->name_ar ?: $c?->name }}</div>
                    @if($c?->name_en && $c->name_en !== $c->name)<div>{{ $c->name_en }}</div>@endif
                    @if($c?->address || $c?->city)<div class="muted">{{ $c->address }} {{ $c->city }}</div>@endif
                    <div class="muted">@if($c?->phone)<bdi dir="ltr">{{ $c->phone }}</bdi>@endif @if($c?->email) · <bdi dir="ltr">{{ $c->email }}</bdi>@endif</div>
                    @if($c?->cr_number)<div class="muted">س.ت / C.R: {{ $c->cr_number }}</div>@endif
                    @if($c?->vat_number)<div class="muted">الرقم الضريبي / VATIN: {{ $c->vat_number }}</div>@endif
                </div>
            </td>
            <td>
                <div class="box">
                    <table>
                        <tr><td class="lbl">تاريخ الإصدار / Issue date</td><td class="num">{{ $inv->issue_date?->format('Y-m-d') }}</td></tr>
                        <tr><td class="lbl">تاريخ الاستحقاق / Due date</td><td class="num">{{ $inv->due_date?->format('Y-m-d') }}</td></tr>
                        @if($inv->period_start)<tr><td class="lbl">الفترة / Period</td><td class="num">{{ $inv->period_start->format('Y-m-d') }} → {{ $inv->period_end?->format('Y-m-d') }}</td></tr>@endif
                        @if($inv->subscription?->plan)<tr><td class="lbl">الباقة / Plan</td><td class="num">{{ $inv->subscription->plan->name }}</td></tr>@endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="grid" style="margin-top:10px">
        <thead><tr>
            <th style="width:6%">#</th><th>الوصف / Description</th>
            <th class="num" style="width:10%">الكمية / Qty</th><th class="num" style="width:16%">السعر / Unit price</th><th class="num" style="width:16%">المبلغ / Amount</th>
        </tr></thead>
        <tbody>
        @foreach($inv->items ?? [] as $n => $it)
            <tr><td>{{ $n + 1 }}</td><td>{{ $it['description'] }}</td><td class="num">{{ qty($it['qty']) }}</td>
                <td class="num">{{ money($it['unit_price'], false) }}</td><td class="num">{{ money(\App\Support\Money::mul($it['qty'], $it['unit_price']), false) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <table style="margin-top:8px">
        <tr>
            <td style="width:55%;padding-{{ $end }}:8px">
                @if($inv->notes)<div class="box" style="white-space:pre-line;margin-bottom:6px"><div class="lbl">ملاحظات / Notes</div>{{ $inv->notes }}</div>@endif
                @if($st === 'paid')
                    <div class="box" style="border-color:#198754"><div class="lbl">الدفع / Payment</div>
                        {{ $inv->paid_at?->format('Y-m-d') }}@if($inv->payment_method) · {{ status_label($inv->payment_method) }}@endif @if($inv->payment_reference) · <bdi dir="ltr">{{ $inv->payment_reference }}</bdi>@endif</div>
                @elseif($st === 'cancelled')
                    <div class="box"><div class="lbl">سبب الإلغاء / Cancel reason</div>{{ $inv->cancel_reason }}</div>
                @elseif($s['billing_bank_details'])
                    <div class="box" style="white-space:pre-line"><div class="lbl">بيانات الدفع / Payment details</div>{{ $s['billing_bank_details'] }}</div>
                @endif
            </td>
            <td>
                <table class="totals">
                    <tr><td>المجموع / Subtotal</td><td class="num">{{ money($inv->subtotal, false) }}</td></tr>
                    @if(\App\Support\Money::isPositive($inv->discount))<tr><td>الخصم / Discount</td><td class="num">-{{ money($inv->discount, false) }}</td></tr>@endif
                    <tr><td>الخاضع للضريبة / Taxable</td><td class="num">{{ money(\App\Support\Money::sub($inv->subtotal, $inv->discount), false) }}</td></tr>
                    <tr><td>ضريبة القيمة المضافة / VAT {{ $rate }}%</td><td class="num">{{ money($inv->tax_amount, false) }}</td></tr>
                    <tr class="grand"><td>الإجمالي / Total</td><td class="num">{{ money($inv->amount) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($s['billing_terms'])<div class="muted" style="margin-top:14px;border-top:1px solid #ddd;padding-top:6px;white-space:pre-line">{{ $s['billing_terms'] }}</div>@endif
    <div class="muted" style="margin-top:10px;text-align:center">{{ $issuer }} · {{ $inv->number }} · {{ now()->format('Y-m-d H:i') }}</div>
</div>
</body>
</html>
