@props(['title', 'subtitle' => null])
<x-dashboard-layout :title="$title" :workspace="true">
    <div class="merchant-design space-y-6">
        <header class="workspace-page-heading">
            <div><h1 class="text-3xl font-semibold leading-snug text-stone-900">{{ $title }}</h1>
            @if($subtitle)<p class="mt-2 text-sm leading-relaxed text-stone-500">{{ $subtitle }}</p>@endif</div>
            @isset($actions)<div class="workspace-page-actions">{{ $actions }}</div>@endisset
        </header>
        {{ $slot }}
    </div>
</x-dashboard-layout>
