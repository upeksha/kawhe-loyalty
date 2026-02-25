@props(['title' => '', 'class' => ''])

<div {{ $attributes->merge(['class' => 'mb-8 last:mb-0 ' . $class]) }}>
    @if($title)
        <h3 class="text-sm font-semibold text-stone-700 mb-4 flex items-center gap-2">
            <span class="w-1 h-5 rounded-full bg-brand-500"></span>
            {{ $title }}
        </h3>
    @endif
    <div class="rounded-xl bg-stone-50/70 border border-stone-200/80 p-5 sm:p-6">
        {{ $slot }}
    </div>
</div>
