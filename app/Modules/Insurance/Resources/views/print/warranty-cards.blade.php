@extends('layouts.print')
@section('title', __('Warranty cards').' — '.$inv->displayNumber())
@section('pdf-url', route('invoices.warranties', [$inv, 'pdf']))
@section('styles')
    .wr-page { page-break-after: always; }
    .wr-page:last-child { page-break-after: auto; }
    .card-frame { border: 3px double #1f6feb; border-radius: 10px; padding: 10px 12px; }
    .big { font-size: 15px; font-weight: bold; }
    .stamp { text-align: center; font-weight: bold; padding: 4px; border-radius: 6px; margin: 6px 0; }
    .stamp.ok { border: 2px solid #198754; color: #198754; }
    .stamp.no { border: 2px solid #b02a37; color: #b02a37; }
    .signs td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 8px; }
@endsection
@section('content')
@foreach($warranties as $w)
    <div class="wr-page">@include('insurance::print._warranty-card-body', ['w' => $w])</div>
@endforeach
@endsection
