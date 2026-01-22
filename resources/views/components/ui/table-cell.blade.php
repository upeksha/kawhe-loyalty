@props(['class' => ''])

<td {{ $attributes->merge(['class' => 'px-6 py-4 whitespace-nowrap text-sm text-stone-900 ' . $class]) }}>
    {{ $slot }}
</td>
