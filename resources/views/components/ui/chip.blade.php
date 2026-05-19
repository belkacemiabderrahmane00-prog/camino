@props([
    'active' => false,
    'icon' => null,
])

@php
    $base = 'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-medium transition cursor-pointer whitespace-nowrap';

    $classes = $active
        ? 'bg-primary text-slate-900 shadow-sm shadow-primary/40'
        : 'bg-slate-900/70 dark:bg-slate-900/80 border border-slate-700/80 text-slate-200 hover:border-primary/70 hover:text-primary';

    $classes = $base.' '.$classes;
@endphp

<button {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
        <span class="material-symbols-outlined text-[16px]">{{ $icon }}</span>
    @endif
    {{ $slot }}
</button>

