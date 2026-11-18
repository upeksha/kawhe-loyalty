@props(['visual' => false, 'compact' => false, 'background' => null])

@php($presets = ['Forest' => '#263E30', 'Cream' => '#FAEDD7', 'Coffee' => '#4B3025', 'Berry' => '#8A2453'])

<div @class(['flex flex-wrap', 'mt-3 gap-2' => !$compact, 'kawhe-editor-swatches' => $compact]) role="group" aria-label="Card colour presets">
    @foreach($presets as $label => $colour)
        <button type="button" aria-label="{{ $label }} card colour" title="{{ $label }}" @class(['inline-flex min-h-11 items-center text-stone-700', 'min-w-11 flex-col gap-1 rounded-md px-1 py-1 text-xs' => $visual, 'gap-2 rounded-lg border border-stone-200 px-3 text-sm' => !$visual])
            @if($visual) :aria-pressed="bgColor.toUpperCase() === '{{ $colour }}'" @endif @click="const field = $el.closest('form').querySelector('[name=background_color]'); field.value = '{{ $colour }}'; field.dispatchEvent(new Event('input', { bubbles: true }));">
            <span @class(['rounded-full border border-black/10', 'size-7' => $visual, 'size-5' => !$visual])
                @if($visual && !$compact) :class="bgColor.toUpperCase() === '{{ $colour }}' ? 'ring-2 ring-brand-700 ring-offset-2' : ''" @endif style="background-color: {{ $colour }}" aria-hidden="true">
                @if($compact)
                    <img x-show="bgColor.toUpperCase() === '{{ $colour }}'" x-cloak src="{{ asset('images/onboarding/colour-check.svg') }}" @class(['brightness-0' => $label === 'Cream']) alt="">
                @endif
            </span>
            {{ $label }}
        </button>
    @endforeach
    @if($compact)
        <label class="relative inline-flex min-h-11 min-w-11 cursor-pointer flex-col items-center gap-1 rounded-md px-1 py-1 text-xs focus-within:ring-2 focus-within:ring-brand-600">
            <span class="grid size-7 place-items-center rounded-full border bg-white" :style="!@js(array_values($presets)).includes(bgColor.toUpperCase()) ? { borderColor: bgColor } : {}" aria-hidden="true">
                <img src="{{ asset('images/onboarding/colour-plus.svg') }}" alt="">
            </span>
            <span>Custom</span>
            <input type="color" id="background_color" name="background_color" x-ref="bgColor" value="{{ $background }}" aria-label="Choose custom card colour" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
        </label>
    @endif
</div>
