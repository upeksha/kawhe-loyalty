<div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Card colour presets">
    @foreach(['Forest' => '#263E30', 'Cream' => '#FAEDD7', 'Coffee' => '#4B3025', 'Berry' => '#8A2453'] as $label => $colour)
        <button type="button" aria-label="{{ $label }} card colour" title="{{ $label }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-stone-200 px-3 text-sm text-stone-700" @click="const field = $el.closest('form').querySelector('[name=background_color]'); field.value = '{{ $colour }}'; field.dispatchEvent(new Event('input', { bubbles: true }));">
            <span class="h-5 w-5 rounded-full border border-black/10" style="background-color: {{ $colour }}" aria-hidden="true"></span>{{ $label }}
        </button>
    @endforeach
</div>
