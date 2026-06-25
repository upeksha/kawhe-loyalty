@props([
    'eyebrow',
    'title',
    'description' => null,
])

<x-ui.section-panel {{ $attributes->merge(['class' => 'p-6 sm:p-8']) }}>
    <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-brand-600">{{ $eyebrow }}</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{{ $title }}</h2>
            @if($description)
                <p class="mt-3 max-w-xl text-base leading-7 text-stone-600">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="grid gap-3 sm:grid-cols-2 xl:w-[34rem]">
                {{ $actions }}
            </div>
        @endisset
    </div>
</x-ui.section-panel>
