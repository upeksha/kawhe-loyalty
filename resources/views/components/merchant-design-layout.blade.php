@props(['title', 'subtitle' => null])
<x-dashboard-layout :title="$title">
    <div class="merchant-design space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold leading-snug text-stone-900 sm:text-4xl">{{ $title }}</h1>
            @if($subtitle)<p class="text-base text-stone-600">{{ $subtitle }}</p>@endif
        </header>
        <x-merchant-navigation />
        {{ $slot }}
    </div>
</x-dashboard-layout>
