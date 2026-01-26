@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white border border-stone-200', 'direction' => 'down'])

@php
$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};

// Normalize direction to ensure string comparison works
$direction = (string) $direction;

// Build alignment and direction classes together
$isUp = ($direction === 'up' || $direction === 'UP' || strtolower($direction) === 'up');

if ($isUp) {
    // Opening upward - NO position classes, we'll use inline styles only
    $positionClasses = ''; // Empty - no top-full or bottom-full classes
    $alignmentClasses = match ($align) {
        'left' => 'ltr:origin-bottom-left rtl:origin-bottom-right start-0 left-0',
        'top' => 'origin-bottom',
        default => 'ltr:origin-bottom-right rtl:origin-bottom-left end-0 right-0',
    };
} else {
    // Opening downward (default) - position below the trigger
    $positionClasses = 'top-full mt-2';
    $alignmentClasses = match ($align) {
        'left' => 'ltr:origin-top-left rtl:origin-top-right start-0 left-0',
        'top' => 'origin-top',
        default => 'ltr:origin-top-right rtl:origin-top-left end-0 right-0',
    };
}
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false" style="overflow: visible; z-index: 100;">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute {{ $positionClasses }} {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }}"
            @if($isUp)
            style="z-index: 10000 !important; bottom: 100% !important; margin-bottom: 0.5rem !important; top: auto !important; left: 0 !important;"
            @else
            style="z-index: 10000;"
            @endif
            @click="open = false">
        <div class="rounded-md ring-1 ring-stone-200 ring-opacity-50 shadow-lg {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
