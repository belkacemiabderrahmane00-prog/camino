@props(['eyebrow' => null, 'title', 'subtitle' => null, 'href' => null, 'linkLabel' => null])
@php $linkLabel = $linkLabel ?? __('Voir tout'); @endphp
<div {{ $attributes->merge(['class' => 'flex items-end justify-between gap-4 mb-5']) }}>
    <div>
        @if($eyebrow)<p class="eyebrow mb-1.5">{{ $eyebrow }}</p>@endif
        <h2 class="display text-2xl sm:text-3xl">{{ $title }}</h2>
        @if($subtitle)<p class="mt-1.5 text-sm text-ink-muted max-w-xl">{{ $subtitle }}</p>@endif
    </div>
    @if($href)
        <a href="{{ $href }}" class="btn btn-sm btn-soft shrink-0">{{ $linkLabel }} <span class="material-symbols-outlined" style="font-size:16px">arrow_forward</span></a>
    @endif
</div>
