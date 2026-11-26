{{--
    Vehicle top view with clickable zones (DVI). $lights: [zone => green|yellow|red]; $interactive: dispatch "zone-picked".
--}}
@php
    $lights = $lights ?? [];
    $interactive = $interactive ?? false;
    $fill = ['green' => '#22c55e', 'yellow' => '#facc15', 'red' => '#ef4444'];
    $Z = \App\Modules\Workshop\Models\Inspection::class;
    $shapes = [
        'front_bumper' => '<rect x="52" y="12" width="96" height="14" rx="7"/>',
        'headlight_l' => '<rect x="50" y="29" width="28" height="10" rx="4"/>',
        'headlight_r' => '<rect x="122" y="29" width="28" height="10" rx="4"/>',
        'hood' => '<path d="M52 42 H148 L144 104 H56 Z"/>',
        'windshield' => '<path d="M57 108 H143 L134 136 H66 Z"/>',
        'roof' => '<rect x="64" y="139" width="72" height="90" rx="6"/>',
        'rear_glass' => '<path d="M66 232 H134 L143 258 H57 Z"/>',
        'trunk' => '<path d="M56 261 H144 L148 318 H52 Z"/>',
        'taillight_l' => '<rect x="50" y="321" width="28" height="10" rx="4"/>',
        'taillight_r' => '<rect x="122" y="321" width="28" height="10" rx="4"/>',
        'rear_bumper' => '<rect x="52" y="334" width="96" height="14" rx="7"/>',
        'fender_fl' => '<rect x="33" y="42" width="17" height="62" rx="5"/>',
        'fender_fr' => '<rect x="150" y="42" width="17" height="62" rx="5"/>',
        'door_fl' => '<rect x="33" y="108" width="17" height="60" rx="4"/>',
        'door_fr' => '<rect x="150" y="108" width="17" height="60" rx="4"/>',
        'door_rl' => '<rect x="33" y="171" width="17" height="58" rx="4"/>',
        'door_rr' => '<rect x="150" y="171" width="17" height="58" rx="4"/>',
        'quarter_rl' => '<rect x="33" y="232" width="17" height="86" rx="5"/>',
        'quarter_rr' => '<rect x="150" y="232" width="17" height="86" rx="5"/>',
        'mirror_l' => '<path d="M33 116 L16 112 L16 126 L33 124 Z"/>',
        'mirror_r' => '<path d="M167 116 L184 112 L184 126 L167 124 Z"/>',
        'wheel_fl' => '<rect x="14" y="52" width="16" height="40" rx="6"/>',
        'wheel_fr' => '<rect x="170" y="52" width="16" height="40" rx="6"/>',
        'wheel_rl' => '<rect x="14" y="258" width="16" height="40" rx="6"/>',
        'wheel_rr' => '<rect x="170" y="258" width="16" height="40" rx="6"/>',
    ];
@endphp
<div class="dvi-diagram {{ $interactive ? 'is-interactive' : '' }}">
    <svg viewBox="0 0 200 360" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ __('Vehicle diagram') }}">
        <rect x="40" y="18" width="120" height="324" rx="44" fill="none" stroke="currentColor" stroke-opacity=".25" stroke-width="2"/>
        @foreach($shapes as $zone => $shape)
            @php $l = $lights[$zone] ?? null; @endphp
            <g class="dvi-zone {{ $l ? 'has-'.$l : '' }}" data-zone="{{ $zone }}" @if($l) style="--zone-fill: {{ $fill[$l] }}" @endif
               @if($interactive) tabindex="0" role="button" @click="$dispatch('zone-picked', { zone: '{{ $zone }}' })" @keydown.enter="$dispatch('zone-picked', { zone: '{{ $zone }}' })" @endif>
                <title>{{ $Z::zoneLabel($zone) }}</title>
                {!! $shape !!}
            </g>
        @endforeach
        <text x="100" y="9" text-anchor="middle" font-size="8" fill="currentColor" opacity=".5">{{ __('FRONT') }}</text>
        <text x="100" y="358" text-anchor="middle" font-size="8" fill="currentColor" opacity=".5">{{ __('REAR') }}</text>
    </svg>
    <div class="dvi-chips">
        @foreach(array_diff(array_keys($Z::ZONES), $Z::DRAWN) as $zone)
            @php $l = $lights[$zone] ?? null; @endphp
            <span class="dvi-chip {{ $l ? 'has-'.$l : '' }}" @if($interactive) role="button" tabindex="0" @click="$dispatch('zone-picked', { zone: '{{ $zone }}' })" @endif>{{ $Z::zoneLabel($zone) }}</span>
        @endforeach
    </div>
</div>
