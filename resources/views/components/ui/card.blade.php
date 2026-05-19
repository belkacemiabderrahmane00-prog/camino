@props([
    'padding' => 'md',
    'bordered' => true,
    'glass' => false,
])

@php
    $base = 'rounded-3xl shadow-lg shadow-slate-900/10 dark:shadow-black/40';
    $paddingClasses = [
        'sm' => 'p-3',
        'md' => 'p-4 sm:p-5',
        'lg' => 'p-6',
    ];

    $surface = $glass
        ? 'bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border border-slate-200/70 dark:border-slate-800/80'
        : 'bg-slate-900/90 dark:bg-slate-950/90 border border-slate-800/80';

    if (! $bordered) {
        $surface = $glass
            ? 'bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl'
            : 'bg-slate-900/90 dark:bg-slate-950/90';
    }

    $classes = implode(' ', [$base, $surface, $paddingClasses[$padding] ?? $paddingClasses['md']]);
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</div>

