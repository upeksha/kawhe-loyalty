@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div>
        <h1 class="text-2xl font-bold text-stone-900">{{ $title }}</h1>
        @if($description)
            <p class="mt-1 text-sm text-stone-600">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 items-center gap-3">
            {{ $actions }}
        </div>
    @endisset
</div>
