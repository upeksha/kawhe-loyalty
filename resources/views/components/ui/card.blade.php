@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'bg-white rounded-lg shadow-sm border border-stone-200 ' . $class]) }}>
    {{ $slot }}
</div>
