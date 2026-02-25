@props(['id' => '', 'name' => '', 'type' => 'text', 'label' => '', 'hint' => null, 'error' => null])

@php
    $inputClass = 'block w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 placeholder-stone-400 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 disabled:bg-stone-50 disabled:text-stone-500';
@endphp

<div class="space-y-1.5">
    @if($label)
        <label for="{{ $id ?: $name }}" class="block text-sm font-medium text-stone-700">{{ $label }}</label>
    @endif
    <input
        type="{{ $type }}"
        id="{{ $id ?: $name }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => $inputClass]) }}
    />
    @if($hint)
        <p class="text-xs text-stone-500">{{ $hint }}</p>
    @endif
    @if($error)
        <x-input-error :messages="$error" class="mt-1" />
    @endif
</div>
