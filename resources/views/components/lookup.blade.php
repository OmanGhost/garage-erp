@props(['name', 'type', 'value' => null, 'text' => '', 'placeholder' => null, 'required' => false, 'params' => '', 'id' => null, 'size' => null, 'wire' => null])
@php $id = $id ?? 'lk_'.str_replace(['[', ']', '.'], '_', $name).'_'.\Illuminate\Support\Str::random(4); @endphp
<div class="lookup" data-lookup="{{ $type }}" data-params="{{ $params }}" {{ $attributes->only(['x-on:lookup-selected', '@lookup-selected', 'class']) }}>
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ old($name, $value) }}" @if($wire) wire:model.live="{{ $wire }}" @endif {{ $attributes->only(['x-model']) }}>
    <input type="text" class="form-control {{ $size ? 'form-control-'.$size : '' }} @error($name) is-invalid @enderror" value="{{ $text }}" autocomplete="off"
           placeholder="{{ $placeholder ?? __('Type to search...') }}" @if($required) required @endif {{ $attributes->only(['x-bind:value', ':value']) }}>
    <div class="lookup-menu"></div>
</div>
