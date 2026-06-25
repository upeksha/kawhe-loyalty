@props([
    'heading',
    'description' => null,
])

<x-ui.card {{ $attributes->merge(['class' => 'p-8 text-center']) }}>
    @isset($icon)
        <div class="mb-4 flex justify-center">
            {{ $icon }}
        </div>
    @endisset

    <h2 class="text-lg font-semibold text-stone-900">{{ $heading }}</h2>

    @if($description)
        <p class="mt-2 text-sm text-stone-600 max-w-md mx-auto">{{ $description }}</p>
    @endif

    @if(trim($slot) !== '')
        <div class="mt-6">
            {{ $slot }}
        </div>
    @endif
</x-ui.card>
