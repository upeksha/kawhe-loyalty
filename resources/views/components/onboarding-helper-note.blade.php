@props(['class' => ''])

<p {{ $attributes->merge(['class' => 'mt-1.5 text-sm text-stone-500 ' . $class]) }}>
    {{ $slot }}
</p>
