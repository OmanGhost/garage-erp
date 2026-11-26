@extends('layouts.print')
@section('title', $t->number)
@section('pdf-url', route('inventory.transfers.print', [$t, $type, 'pdf']))
@section('styles')
    .signs td { width: 33%; text-align: center; vertical-align: bottom; padding: 0 6px; }
    .stamp { text-align: center; font-weight: bold; padding: 5px; border-radius: 6px; margin: 6px 0; }
    .stamp.ok { border: 2px solid #198754; color: #198754; }
    .stamp.wait { border: 2px solid #b8860b; color: #8a6d00; }
    .stamp.no { border: 2px solid #b02a37; color: #b02a37; }
    .short { color: #b02a37; font-weight: bold; }
@endsection
@section('content')
@php
    $completed = $type === 'completed';
    $statusText = ['draft' => 'مسودة / Draft', 'in_transit' => 'قيد النقل / In transit', 'received' => 'مستلم / Received', 'cancelled' => 'ملغى / Cancelled'][$t->status] ?? $t->status;
@endphp
<div class="doc-title">
    {{ $completed ? 'سند نقل مخزون مكتمل' : 'طلب نقل مخزون' }}
    <span class="bilingual">{{ $completed ? 'Completed Stock Transfer' : 'Stock Transfer Request' }}</span>
</div>
@if($t->status === 'cancelled')
    <div class="stamp no">ملغى / CANCELLED @if($t->cancel_reason)— {{ $t->cancel_reason }}@endif</div>
@elseif($completed && $t->status !== 'received')
    <div class="stamp wait">لم يُستلم بعد / NOT RECEIVED YET — {{ $statusText }}</div>
@elseif($completed)
    <div class="stamp ok">تم الاستلام في المستودع / RECEIVED</div>
@endif

<table class="grid" style="margin-bottom:8px">
    <tr><th>رقم النقل / Transfer No.</th><td><b>{{ $t->number }}</b></td><th>التاريخ / Date</th><td>{{ $t->transfer_date?->format('Y-m-d') }}</td></tr>
    <tr><th>من مستودع / From</th><td><b>{{ $t->fromWarehouse->displayName() }}</b></td><th>إلى مستودع / To</th><td><b>{{ $t->toWarehouse->displayName() }}</b></td></tr>
    <tr><th>طلبه / Requested by</th><td>{{ $t->creator?->name ?? '—' }} · {{ $t->created_at?->format('Y-m-d H:i') }}</td><th>الحالة / Status</th><td>{{ $statusText }}</td></tr>
    @if($completed || $t->sent_at)
        <tr><th>أرسله / Dispatched by</th><td>{{ $t->sender?->name ?? '—' }} @if($t->sent_at)· {{ $t->sent_at->format('Y-m-d H:i') }}@endif</td>
            <th>استلمه / Received by</th><td>{{ $t->receiver?->name ?? '—' }} @if($t->received_at)· {{ $t->received_at->format('Y-m-d H:i') }}@endif</td></tr>
    @endif
</table>

<table class="grid">
    @if($completed)
        <tr><th>#</th><th>رقم القطعة / Part no.</th><th>الصنف / Item</th><th class="num">المرسل<br>Sent</th><th class="num">المستلم<br>Received</th><th class="num">الفرق<br>Variance</th><th class="num">التكلفة<br>Unit cost</th><th class="num">القيمة<br>Value</th></tr>
        @foreach($t->items as $i => $it)
            @php $rec = $it->received_qty !== null ? (float) $it->received_qty : null; $diff = $rec !== null ? $rec - (float) $it->qty : null; @endphp
            <tr><td>{{ $i + 1 }}</td><td dir="ltr">{{ $it->part->sku }}</td><td>{{ $it->part->displayName() }}</td><td class="num">{{ qty($it->qty) }}</td>
                <td class="num">{{ $rec !== null ? qty($rec) : '—' }}</td><td class="num {{ $diff && $diff < 0 ? 'short' : '' }}">{{ $diff !== null ? ($diff == 0 ? '—' : qty($diff)) : '—' }}</td>
                <td class="num">{{ money($it->unit_cost, false) }}</td><td class="num">{{ money((float) ($rec ?? $it->qty) * (float) $it->unit_cost, false) }}</td></tr>
        @endforeach
        <tr><th colspan="3" style="text-align:{{ is_rtl() ? 'left' : 'right' }}">الإجمالي / Total</th><th class="num">{{ qty($t->items->sum('qty')) }}</th>
            <th class="num">{{ qty($t->items->sum(fn ($i) => (float) ($i->received_qty ?? 0))) }}</th><th></th><th></th>
            <th class="num">{{ money($t->items->sum(fn ($i) => (float) ($i->received_qty ?? $i->qty) * (float) $i->unit_cost), false) }}</th></tr>
    @else
        <tr><th>#</th><th>رقم القطعة / Part no.</th><th>الصنف / Item</th><th>الوحدة / Unit</th><th class="num">الكمية المطلوبة<br>Requested qty</th><th>ملاحظات / Notes</th></tr>
        @foreach($t->items as $i => $it)
            <tr><td>{{ $i + 1 }}</td><td dir="ltr">{{ $it->part->sku }}</td><td>{{ $it->part->displayName() }}</td><td>{{ $it->part->unit ? status_label($it->part->unit) : '—' }}</td><td class="num"><b>{{ qty($it->qty) }}</b></td><td></td></tr>
        @endforeach
        <tr><th colspan="4" style="text-align:{{ is_rtl() ? 'left' : 'right' }}">إجمالي الكمية / Total qty</th><th class="num">{{ qty($t->items->sum('qty')) }}</th><th></th></tr>
    @endif
</table>
@if($t->notes)<p class="muted" style="margin-top:6px">ملاحظات / Notes: {{ $t->notes }}</p>@endif
@php $logNotes = $t->events->whereIn('event', $completed ? ['dispatched', 'received', 'note'] : ['note'])->filter(fn ($e) => filled($e->notes))->sortBy('id'); @endphp
@if($logNotes->isNotEmpty())
    <table class="grid" style="margin-top:6px"><tr><th colspan="3">ملاحظات المتابعة / Follow-up notes</th></tr>
        @foreach($logNotes as $e)<tr><td style="width:22%">{{ $e->created_at?->format('Y-m-d H:i') }}</td><td style="width:20%">{{ $e->user?->name }}</td><td>{{ $e->notes }}</td></tr>@endforeach
    </table>
@endif
@if($completed && $t->items->contains(fn ($i) => $i->received_qty !== null && (float) $i->received_qty < (float) $i->qty))
    <p class="muted">النقص في الكميات المستلمة سُجّل كخسارة مخزون. / Shortages were posted as inventory loss.</p>
@endif

<table class="signs" style="margin-top:26px"><tr>
    @if($completed)
        <td><div class="sign"></div>أمين المستودع المرسل / Sending storekeeper<br><span class="muted">{{ $t->sender?->name }}</span></td>
        <td><div class="sign"></div>الناقل / السائق — Carrier / driver</td>
        <td><div class="sign"></div>أمين المستودع المستلم / Receiving storekeeper<br><span class="muted">{{ $t->receiver?->name }}</span></td>
    @else
        <td><div class="sign"></div>مقدم الطلب / Requested by<br><span class="muted">{{ $t->creator?->name }}</span></td>
        <td><div class="sign"></div>الاعتماد / Approved by</td>
        <td><div class="sign"></div>أمين المستودع المرسل / Sending storekeeper</td>
    @endif
</tr></table>
@endsection
