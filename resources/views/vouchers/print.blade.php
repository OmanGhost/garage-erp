@php
    /** @var \App\Support\Vouchers\Voucher $v */
    $company = tenant()->company();
    $nameAr = $company?->name_ar ?: $company?->name;
    $nameEn = $company?->name_en ?: $company?->name;
    [$rials, $baisa] = $v->split();
    [$wordsAr, $wordsEn] = $v->words();
    $paper = request('paper') === 'a4' ? 'a4' : 'a5';
    $copies = $paper === 'a4' ? [['الأصل', 'ORIGINAL'], ['صورة', 'COPY']] : [['الأصل', 'ORIGINAL']];
    $accent = ['receipt' => '#0f5132', 'payment' => '#7a1f1f', 'transfer' => '#0b3a6e'][$v->kind] ?? '#222';
    $qr = \App\Support\Codes::qr($v->verification((string) $nameEn), 3);
    $methods = [['cash', 'نقداً', 'Cash'], ['cheque', 'شيك', 'Cheque'], ['bank_transfer', 'تحويل بنكي', 'Bank transfer'], ['card', 'بطاقة', 'Card']];
    $methodKnown = in_array($v->method, array_column($methods, 0), true);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $v->titleAr }} {{ $v->number }}</title>
    <style>
        @page { size: {{ $paper === 'a4' ? 'A4 portrait' : 'A5 landscape' }}; margin: 6mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; background: #d9dde3; color: #111; font-family: "Simplified Arabic", "Traditional Arabic", "Segoe UI", Tahoma, Arial, sans-serif; font-size: 12.5px; }
        .toolbar { text-align: center; padding: 10px; font-family: "Segoe UI", Tahoma, sans-serif; }
        .toolbar a, .toolbar button { display: inline-block; margin: 2px; padding: 6px 14px; border: 1px solid #888; border-radius: 6px; background: #fff; color: #111; text-decoration: none; font-size: 13px; cursor: pointer; }
        .toolbar a.on { background: {{ $accent }}; border-color: {{ $accent }}; color: #fff; }
        .sheet { width: {{ $paper === 'a4' ? '198mm' : '198mm' }}; margin: 0 auto 12px; background: #fff; box-shadow: 0 4px 18px rgba(0,0,0,.15); }

        /* official frame */
        .voucher { position: relative; height: {{ $paper === 'a4' ? '138mm' : '136mm' }}; padding: 3.2mm; overflow: hidden; }
        .frame { position: relative; height: 100%; border: 2.2px solid {{ $accent }}; outline: 1px solid {{ $accent }}; outline-offset: -5px; padding: 4mm 5mm;
            background:
                repeating-linear-gradient(45deg, rgba(0,0,0,.018) 0 2px, transparent 2px 7px),
                repeating-linear-gradient(-45deg, rgba(0,0,0,.018) 0 2px, transparent 2px 7px); }
        .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
        .watermark span { font-size: 54px; font-weight: 700; color: {{ $accent }}; opacity: .05; transform: rotate(-18deg); white-space: nowrap; }
        .watermark.void span { color: #c00; opacity: .25; font-size: 64px; }

        .head { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 4mm; border-bottom: 1.5px solid {{ $accent }}; padding-bottom: 2mm; }
        .head .ar { text-align: right; line-height: 1.35; }
        .head .en { text-align: left; direction: ltr; line-height: 1.35; font-family: "Segoe UI", Tahoma, Arial, sans-serif; }
        .head .name { font-size: 15px; font-weight: 700; color: {{ $accent }}; }
        .head .meta { font-size: 10px; color: #444; }
        .emblem { width: 22mm; height: 22mm; display: flex; align-items: center; justify-content: center; }
        .emblem img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .emblem .seal { width: 20mm; height: 20mm; border-radius: 50%; border: 2px double {{ $accent }}; display: flex; align-items: center; justify-content: center;
            text-align: center; font-size: 9px; font-weight: 700; color: {{ $accent }}; line-height: 1.2; padding: 2mm; }

        .titlebar { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 4mm; margin: 2.5mm 0 2mm; }
        .no { font-size: 12px; }
        .no b { font-family: "Courier New", monospace; font-size: 17px; color: #c00; letter-spacing: 1px; direction: ltr; display: inline-block; }
        .title { text-align: center; border: 2px solid {{ $accent }}; border-radius: 4px; padding: 1mm 8mm; background: {{ $accent }}; color: #fff; }
        .title .t-ar { font-size: 20px; font-weight: 700; line-height: 1.2; }
        .title .t-en { font-size: 10px; letter-spacing: 2px; font-family: "Segoe UI", Tahoma, sans-serif; text-transform: uppercase; }
        .date { display: flex; justify-content: flex-end; align-items: center; gap: 1.5mm; font-size: 11px; }
        .date .cell { border: 1px solid #333; min-width: 9mm; text-align: center; padding: .6mm 1mm; font-family: "Courier New", monospace; font-weight: 700; font-size: 13px; background: #fff; }
        .copy-tag { position: absolute; top: 3mm; left: 6mm; font-size: 9px; font-weight: 700; letter-spacing: 1px; color: {{ $accent }}; border: 1px solid {{ $accent }}; padding: .2mm 2mm; border-radius: 3px; background: #fff; }

        .amount { display: flex; align-items: stretch; gap: 3mm; margin-bottom: 2mm; }
        .money { display: grid; grid-template-columns: auto auto; border: 2px solid #222; direction: ltr; background: #fff; }
        .money div { padding: .5mm 3mm; text-align: center; }
        .money .lbl { font-size: 9.5px; background: #eef0f3; border-bottom: 1px solid #222; font-weight: 700; }
        .money .val { font-family: "Courier New", monospace; font-size: 18px; font-weight: 700; }
        .money .r { border-right: 1px solid #222; }
        .words { flex: 1; border: 1px solid #999; padding: 1mm 3mm; background: #fffdf5; display: flex; flex-direction: column; justify-content: center; }
        .words .w-ar { font-weight: 700; font-size: 13px; }
        .words .w-en { direction: ltr; text-align: left; font-size: 10.5px; color: #333; font-family: "Segoe UI", Tahoma, sans-serif; }

        .line { display: flex; align-items: baseline; gap: 2mm; margin: 1.8mm 0; }
        .line .l { white-space: nowrap; font-weight: 700; }
        .line .l small { font-weight: 400; color: #555; font-family: "Segoe UI", Tahoma, sans-serif; font-size: 9.5px; }
        .line .v { flex: 1; border-bottom: 1px dotted #333; min-height: 5mm; padding: 0 2mm; }
        .checks { display: flex; flex-wrap: wrap; gap: 4mm; align-items: center; }
        .box { display: inline-block; width: 3.6mm; height: 3.6mm; border: 1.2px solid #222; vertical-align: -.6mm; text-align: center; line-height: 3mm; font-size: 11px; font-weight: 700; }

        table.lines { width: 100%; border-collapse: collapse; font-size: 10.5px; margin: 1mm 0; }
        table.lines td { border: 1px solid #aaa; padding: .6mm 2mm; }
        table.lines td.n { direction: ltr; text-align: left; font-family: "Courier New", monospace; white-space: nowrap; width: 30mm; }

        .foot { position: absolute; left: 5mm; right: 5mm; bottom: 3.5mm; background: transparent; }
        .body { max-height: 54mm; overflow: hidden; } /* stays above the signatures / stamp area */
        .signs { display: grid; grid-template-columns: repeat(3, 1fr) 22mm 18mm; gap: 3mm; align-items: end; }
        .signs .qr img { width: 18mm; height: 18mm; display: block; }
        .sign { text-align: center; font-size: 10.5px; font-weight: 700; }
        .sign small { display: block; font-weight: 400; color: #555; font-family: "Segoe UI", Tahoma, sans-serif; font-size: 9px; }
        .sign .space { height: 10mm; border-bottom: 1px solid #333; margin-bottom: 1mm; }
        .stamp { width: 22mm; height: 22mm; border: 1.5px dashed #999; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 9px; color: #888; }
        .issued { display: flex; justify-content: space-between; align-items: center; margin-top: 1mm; font-size: 8px; color: #555; font-family: "Segoe UI", Tahoma, sans-serif; }
        .issued img { width: 15mm; height: 15mm; }
        .cut { text-align: center; color: #999; font-size: 9px; border-top: 1px dashed #aaa; margin: 0 3mm; height: 0; line-height: 0; }
        .cut span { background: #fff; padding: 0 3mm; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; width: auto; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">🖨 طباعة / Print</button>
    <a href="{{ request()->fullUrlWithQuery(['paper' => 'a5']) }}" class="{{ $paper === 'a5' ? 'on' : '' }}">A5 (أفقي)</a>
    <a href="{{ request()->fullUrlWithQuery(['paper' => 'a4']) }}" class="{{ $paper === 'a4' ? 'on' : '' }}">A4 (أصل + صورة)</a>
    <button onclick="window.close()">✕ إغلاق / Close</button>
</div>

<div class="sheet">
@foreach($copies as $ci => [$copyAr, $copyEn])
    @if($ci > 0)<div class="cut"><span>✂ قص هنا / cut here</span></div>@endif
    <div class="voucher">
        <div class="frame">
            <div class="watermark {{ $v->cancelled ? 'void' : '' }}"><span>{{ $v->cancelled ? 'ملغى · CANCELLED' : $nameEn }}</span></div>
            <span class="copy-tag">{{ $copyAr }} · {{ $copyEn }}</span>

            {{-- header: Arabic | emblem | English --}}
            <div class="head">
                <div class="ar">
                    <div class="name">{{ $nameAr }}</div>
                    <div class="meta">@if($company?->cr_number)س.ت: {{ $company->cr_number }}@endif @if($company?->vat_number) · الرقم الضريبي: {{ $company->vat_number }}@endif</div>
                    <div class="meta">{{ $company?->address }} {{ $company?->city }} — سلطنة عُمان</div>
                </div>
                <div class="emblem">
                    @if($company?->logoUrl())
                        <img src="{{ $company->logoUrl() }}" alt="">
                    @else
                        <div class="seal">{{ \Illuminate\Support\Str::limit($nameAr, 28) }}</div>
                    @endif
                </div>
                <div class="en">
                    <div class="name">{{ $nameEn }}</div>
                    <div class="meta">@if($company?->cr_number)C.R. {{ $company->cr_number }}@endif @if($company?->vat_number) · VATIN {{ $company->vat_number }}@endif</div>
                    <div class="meta">@if($company?->phone)Tel {{ $company->phone }}@endif @if($company?->email) · {{ $company->email }}@endif</div>
                </div>
            </div>

            {{-- title, number, date --}}
            <div class="titlebar">
                <div class="no">رقم السند / No.: <b>{{ $v->number }}</b></div>
                <div class="title"><div class="t-ar">{{ $v->titleAr }}</div><div class="t-en">{{ $v->titleEn }}</div></div>
                <div class="date">التاريخ / Date
                    <span class="cell">{{ $v->date?->format('d') }}</span><span class="cell">{{ $v->date?->format('m') }}</span><span class="cell" style="min-width:13mm">{{ $v->date?->format('Y') }}</span>
                </div>
            </div>

            <div class="body">
            {{-- amount in figures (rial | baisa) and in words --}}
            <div class="amount">
                <div class="money">
                    <div class="lbl r">ريال عماني / R.O.</div><div class="lbl">بيسة / Bz.</div>
                    <div class="val r">{{ number_format($rials) }}</div><div class="val">{{ str_pad((string) $baisa, 3, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="words">
                    <div class="w-ar">{{ $wordsAr }}</div>
                    <div class="w-en">{{ $wordsEn }}</div>
                </div>
            </div>

            @if($v->kind !== 'transfer')
            <div class="line"><span class="l">{{ $v->partyLabelAr }} <small>{{ $v->partyLabelEn }}</small>:</span><span class="v"><b>{{ $v->party }}</b> @if($v->partyExtra)<span style="color:#555;font-size:10.5px">(<bdi dir="ltr">{{ $v->partyExtra }}</bdi>)</span>@endif</span></div>
            @endif
            <div class="line"><span class="l">وذلك عن <small>Being</small>:</span><span class="v">{{ $v->purpose }} @if($v->purposeEn) — <bdi dir="ltr" style="color:#444;font-size:10.5px;font-family:Segoe UI,Tahoma,sans-serif">{{ $v->purposeEn }}</bdi>@endif</span></div>

            @if($v->kind === 'transfer')
                <div class="line"><span class="l">من حساب <small>From</small>:</span><span class="v">{{ $v->account }}</span><span class="l">إلى حساب <small>To</small>:</span><span class="v">{{ $v->toAccount }}</span></div>
                @if($v->reference)<div class="line"><span class="l">المرجع <small>Ref.</small>:</span><span class="v">{{ $v->reference }}</span></div>@endif
            @else
                <div class="line">
                    <span class="l">طريقة الدفع <small>Method</small>:</span>
                    <span class="v checks">
                        @foreach($methods as [$key, $ar, $en])
                            <span><span class="box">{{ $v->method === $key ? '✓' : '' }}</span> {{ $ar }} <small style="color:#555">{{ $en }}</small></span>
                        @endforeach
                        @if($v->method && ! $methodKnown)<span><span class="box">✓</span> {{ status_label($v->method) }}</span>@endif
                    </span>
                </div>
                @if($v->method === 'cheque' || $v->chequeNumber)
                    <div class="line"><span class="l">شيك رقم <small>Cheque no.</small>:</span><span class="v">{{ $v->chequeNumber }}</span><span class="l">بتاريخ <small>Dated</small>:</span><span class="v">{{ $v->chequeDate }}</span></div>
                @endif
                <div class="line">
                    <span class="l">{{ $v->kind === 'receipt' ? 'أودع في' : 'صرف من' }} <small>{{ $v->kind === 'receipt' ? 'Deposited to' : 'Paid from' }}</small>:</span><span class="v">{{ $v->account ?? '—' }}</span>
                    @if($v->reference)<span class="l">المرجع <small>Ref.</small>:</span><span class="v" style="flex:.6">{{ $v->reference }}</span>@endif
                </div>
            @endif

            @if($v->lines)
                <div class="line"><span class="l">التفاصيل <small>Details</small>:</span><span class="v" style="font-size:11px">
                    @foreach($v->lines as [$label, $amt])<span style="white-space:nowrap">{{ $label }}: <b dir="ltr">{{ money($amt, false) }}</b></span>@if(! $loop->last) · @endif @endforeach
                </span></div>
            @endif
            @if($v->notes)<div class="line"><span class="l">ملاحظات <small>Notes</small>:</span><span class="v">{{ $v->notes }}</span></div>@endif
            </div>

            {{-- signatures, stamp, verification --}}
            <div class="foot">
                <div class="signs">
                    @foreach($v->signatures as [$sAr, $sEn])
                        <div class="sign"><div class="space"></div>{{ $sAr }}<small>{{ $sEn }}</small></div>
                    @endforeach
                    <div class="stamp">الختم<br>Stamp</div>
                    <div class="qr"><img src="{{ $qr }}" alt="QR" title="{{ $v->number }}"></div>
                </div>
                <div class="issued">
                    <span>أعده / Prepared by: {{ $v->preparedBy ?? auth()->user()?->name }} · {{ now()->format('Y-m-d H:i') }}</span>
                    <span>صادر إلكترونياً من نظام Garage ERP v{{ \App\Support\AppVersion::version() }}</span>
                </div>
            </div>
        </div>
    </div>
@endforeach
</div>
</body>
</html>
