@props([
    'label',
    'value',
    'tone' => 'bg-brand-50 text-brand-700',
])

<div {{ $attributes->merge(['class' => "rounded-2xl {$tone} p-4"]) }}>
    <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">{{ $label }}</p>
    <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ $value }}</p>
    {{ $slot }}
</div>
