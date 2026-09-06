@props(['place', 'compact' => false, 'distance' => null])
<a href="{{ route('places.show', $place) }}" {{ $attributes->merge(['class' => 'card card-hover overflow-hidden flex ' . ($compact ? 'flex-row items-stretch' : 'flex-col')]) }}>
    <div class="relative {{ $compact ? 'w-28 shrink-0' : '' }}">
        <x-cover :place="$place" :class="$compact ? 'h-full min-h-[6.5rem]' : 'h-44'" />
        <div class="absolute top-2 left-2 flex gap-1">
            @if($place->is_free)
                <span class="badge badge-free shadow-card">{{ __('Gratuit') }}</span>
            @endif
            @if($place->event_end_at)
                <span class="badge badge-event shadow-card">{{ __('Événement') }}</span>
            @endif
        </div>
    </div>
    <div class="p-4 flex-1 min-w-0 flex flex-col gap-1.5">
        <x-category-pill :category="$place->category" size="xs" class="self-start" />
        <p class="font-semibold text-[15px] leading-snug line-clamp-2">{{ $place->title }}</p>
        <p class="text-xs text-ink-muted line-clamp-1">{{ $place->address ?? 'Île-de-France' }}</p>
        <div class="mt-auto pt-1 flex items-center gap-3 text-[11px] text-ink-muted">
            <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px">schedule</span>{{ $place->visit_duration_min ?? 60 }} min</span>
            @if(isset($place->reviews_avg_rating) && $place->reviews_avg_rating)
                <span class="inline-flex items-center gap-1 text-amber-600"><span class="material-symbols-outlined filled" style="font-size:14px">star</span>{{ number_format($place->reviews_avg_rating, 1) }}</span>
            @endif
            @if($distance !== null)
                <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px">directions_walk</span>{{ $distance }}</span>
            @endif
            @if(!$place->is_free && $place->price_level)
                <span class="ml-auto font-semibold text-ink-soft">{{ str_repeat('€', (int) $place->price_level) }}</span>
            @endif
        </div>
    </div>
</a>
