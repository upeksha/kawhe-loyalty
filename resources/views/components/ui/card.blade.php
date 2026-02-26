@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-stone-200/90 ' . $class]) }}>
    {{ $slot }}
</div>
