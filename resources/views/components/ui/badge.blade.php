@props([
    'tone' => 'primary',
    'size' => 'sm',
])

@php
    $base = 'inline-flex items-center justify-center rounded-full font-semibold whitespace-nowrap';

    $tones = [
        'primary' => 'bg-primary/10 text-primary',
        'success' => 'bg-emerald-500/10 text-emerald-400',
        'warning' => 'bg-amber-500/10 text-amber-300',
        'danger'  => 'bg-rose-500/10 text-rose-300',
        'neutral' => 'bg-slate-800 text-slate-200',
    ];

    $sizes = [
        'xs' => 'px-2 py-0.5 text-[10px]',
        'sm' => 'px-2.5 py-0.5 text-[11px]',
        'md' => 'px-3 py-1 text-xs',
    ];

    $classes = implode(' ', [
        $base,
        $tones[$tone] ?? $tones['primary'],
        $sizes[$size] ?? $sizes['sm'],
    ]);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>

