@props(['class' => ''])

<th {{ $attributes->merge(['class' => 'px-6 py-3 text-left text-xs font-semibold text-stone-700 uppercase tracking-wider ' . $class]) }}>
    {{ $slot }}
</th>
