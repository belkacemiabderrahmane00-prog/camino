@props([
    'label' => null,
    'name',
    'type' => 'text',
])

@php
    $id = $attributes->get('id', $name);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1']) }}>
    @if($label)
        <label for="{{ $id }}" class="text-[11px] font-medium text-slate-900 dark:text-slate-200">
            {{ $label }}
        </label>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->except(['class', 'id']) }}
        class="w-full rounded-2xl border-slate-300 bg-white text-xs text-slate-900 placeholder:text-slate-500 focus:ring-primary focus:border-primary px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100"
    >
</div>

