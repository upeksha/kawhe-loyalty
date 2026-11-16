@props(['model' => null])

@php
    $savedTextColour = old('card_text_color', $model?->card_text_color);
    $savedTextColour = is_string($savedTextColour) ? $savedTextColour : '';
@endphp

<div
    class="rounded-xl border border-stone-200 bg-stone-50 p-4"
    x-data="{
        customTextColour: @js($savedTextColour),
        get automaticTextColour() {
            if (this.cardDesign === 'photo') return '#FFFFFF';
            const light = this.contrastRatio(this.bgColor, '#FFFFFF');
            const dark = this.contrastRatio(this.bgColor, '#111827');
            return dark >= 4.5 && dark >= light ? '#111827' : (light >= 4.5 ? '#FFFFFF' : '#000000');
        },
        get selectedTextColour() {
            return /^#[0-9A-Fa-f]{6}$/.test(this.customTextColour) ? this.customTextColour.toUpperCase() : this.automaticTextColour;
        },
        get textColourReadable() {
            return !this.customTextColour || (/^#[0-9A-Fa-f]{6}$/.test(this.customTextColour) && this.contrastRatio(this.bgColor, this.selectedTextColour) >= 4.5
                && (this.cardDesign !== 'photo' || (this.contrastRatio(@js(\App\Support\CardDesign::PHOTO_TEXT_BACKGROUND), this.selectedTextColour) >= 4.5
                    && this.contrastRatio('#000000', this.selectedTextColour) >= 4.5)));
        },
    }"
    x-effect="
        $refs.textColourPicker.setCustomValidity(textColourReadable ? '' : 'Choose a more readable card text colour or use automatic.');
        $dispatch('card-text-colour', textColourReadable ? customTextColour : '');
    "
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <label for="card-text-colour" class="text-sm font-medium text-stone-700">Card text colour</label>
        <button type="button" x-show="customTextColour" x-cloak @click="customTextColour = ''" class="min-h-10 text-sm font-semibold text-brand-700 underline">Use automatic</button>
    </div>
    <div class="mt-2 flex items-center gap-3">
        <input type="hidden" name="card_text_color" id="card-text-colour-value" :value="customTextColour" value="{{ $savedTextColour }}">
        <input id="card-text-colour" x-ref="textColourPicker" type="color" :value="selectedTextColour" @input="customTextColour = $event.target.value.toUpperCase()" @change="customTextColour = $event.target.value.toUpperCase()" aria-describedby="card-text-colour-help" :aria-invalid="!textColourReadable" class="color-swatch-input h-11 w-11 shrink-0 cursor-pointer rounded-full border-2 border-stone-300 bg-transparent p-0">
        <span class="font-mono text-sm text-stone-700" x-text="selectedTextColour"></span>
        <span x-show="textColourReadable" x-cloak class="rounded-lg px-2.5 py-1 text-sm font-semibold" :style="{ color: selectedTextColour, backgroundColor: cardDesign === 'photo' ? '#575757' : bgColor }">Sample text</span>
    </div>
    <p id="card-text-colour-help" class="mt-2 text-sm text-stone-600" aria-live="polite">
        <span x-show="!customTextColour && cardDesign !== 'photo'">Set automatically for readability. Select the colour to use your own.</span>
        <span x-show="!customTextColour && cardDesign === 'photo'" x-cloak>White is selected automatically over the photo. You can choose a colour that stays readable over the photo and the card background.</span>
        <span x-show="customTextColour && textColourReadable" x-cloak>Using your custom colour.</span>
        <span x-show="!textColourReadable" x-cloak class="font-medium text-red-700">This colour is hard to read. Choose a different colour or use automatic.</span>
    </p>
    <x-input-error :messages="$errors->get('card_text_color')" class="mt-2" />
</div>
