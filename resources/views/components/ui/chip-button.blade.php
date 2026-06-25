@props([
    'type' => 'button',
])

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'inline-flex items-center rounded-xl border border-stone-300 bg-stone-50 px-3 py-2.5 text-sm font-medium text-stone-700 transition-colors hover:bg-stone-100 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50']) }}
>
    {{ $slot }}
</button>
