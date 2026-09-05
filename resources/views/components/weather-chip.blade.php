@props(['forecast', 'label' => 'Paris', 'detailed' => false])
@php $current = $forecast['current'] ?? null; @endphp
@if($current)
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 rounded-full bg-white/90 border border-ink/10 pl-2 pr-4 py-1.5 shadow-card']) }} title="{{ $current['label'] }}">
        <span class="h-8 w-8 rounded-full bg-sun-soft text-amber-600 flex items-center justify-center">
            <span class="material-symbols-outlined filled" style="font-size:18px">{{ $current['icon'] }}</span>
        </span>
        <div class="leading-tight">
            <p class="text-sm font-semibold">{{ round($current['temp']) }}° <span class="font-normal text-ink-muted">· {{ $current['label'] }}</span></p>
            @if($detailed && !empty($forecast['days'][0]))
                <p class="text-[11px] text-ink-muted">{{ $label }} · {{ $forecast['days'][0]['tmin'] }}° / {{ $forecast['days'][0]['tmax'] }}° · pluie {{ $forecast['days'][0]['rain_probability'] }} %</p>
            @else
                <p class="text-[11px] text-ink-muted">{{ $label }}, maintenant</p>
            @endif
        </div>
    </div>
@endif
