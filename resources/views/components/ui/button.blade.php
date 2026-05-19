@props([
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-2xl font-semibold transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 disabled:opacity-50 disabled:cursor-not-allowed';

    $variants = [
        'primary' => 'bg-primary text-slate-900 shadow-lg shadow-primary/30 hover:bg-cyan-300',
        'outline' => 'border border-slate-200/80 dark:border-slate-700/80 bg-white/70 dark:bg-slate-900/70 text-slate-800 dark:text-slate-100 hover:border-primary hover:text-primary',
        'ghost' => 'bg-transparent text-slate-700 dark:text-slate-200 hover:bg-slate-100/60 dark:hover:bg-slate-800/60',
        'accent' => 'bg-gradient-to-r from-primary to-camino-accent text-slate-900 shadow-lg shadow-primary/40 hover:from-cyan-300 hover:to-amber-400',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-3 text-sm',
    ];

    $classes = implode(' ', [$base, $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md']]);
@endphp

<button {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>

