@props(['status'])
@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-2xl bg-teal-soft text-teal-dark px-4 py-3 text-sm']) }}>{{ $status }}</div>
@endif
