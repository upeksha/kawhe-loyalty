@props(['model' => null, 'compact' => false])

@php
    $savedTextColour = old('card_text_color', $model?->card_text_color);
    $savedTextColour = is_string($savedTextColour) ? $savedTextColour : '';
@endphp

<div
    @class(['rounded-xl border border-stone-200 bg-stone-50 p-4' => !$compact, 'kawhe-editor-section kawhe-editor-text-colour' => $compact])
    x-data="{
        customTextColour: @js($savedTextColour),
        get textBackground() {
            return this.cardDesign === 'illustrated_coffee' ? '#263E30' : this.bgColor;
        },
        get automaticTextColour() {
            const light = this.contrastRatio(this.textBackground, '#FFFFFF');
            const dark = this.contrastRatio(this.textBackground, '#111827');
            return dark >= 4.5 && dark >= light ? '#111827' : (light >= 4.5 ? '#FFFFFF' : '#000000');
        },
        get selectedTextColour() {
            return /^#[0-9A-Fa-f]{6}$/.test(this.customTextColour) ? this.customTextColour.toUpperCase() : this.automaticTextColour;
        },
        get textColourReadable() {
            return !this.customTextColour || (/^#[0-9A-Fa-f]{6}$/.test(this.customTextColour) && this.contrastRatio(this.textBackground, this.selectedTextColour) >= 4.5
                && (this.cardDesign !== 'photo' || (this.contrastRatio(@js(\App\Support\CardDesign::PHOTO_TEXT_BACKGROUND), this.selectedTextColour) >= 4.5
                    && this.contrastRatio('#000000', this.selectedTextColour) >= 4.5)));
        },
    }"
    x-effect="
        $refs.textColourPicker.setCustomValidity(textColourReadable ? '' : 'Choose a more readable card text colour or use automatic.');
        $dispatch('card-text-colour', textColourReadable ? customTextColour : '');
    "
>
    <input type="hidden" name="card_text_color" id="card-text-colour-value" :value="customTextColour" value="{{ $savedTextColour }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
            <span class="size-6 shrink-0 rounded-full border border-stone-300" :style="{ backgroundColor: selectedTextColour }" aria-hidden="true"></span>
            <div>
                <label for="card-text-colour" class="block text-sm font-semibold text-stone-800">Card text colour</label>
                <p class="mt-0.5 text-xs text-stone-600">
                    <span x-text="customTextColour ? 'Custom' : 'Automatic'">Automatic</span> · <span x-text="selectedTextColour"></span>
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" x-show="customTextColour" x-cloak @click="customTextColour = ''" class="min-h-10 text-sm font-semibold text-brand-700 underline underline-offset-4">Use automatic</button>
            <label class="relative inline-flex min-h-11 min-w-11 cursor-pointer flex-col items-center gap-1 rounded-md px-1 py-1 text-xs text-brand-800 focus-within:ring-2 focus-within:ring-brand-600">
                <span class="grid size-7 place-items-center rounded-full border border-stone-300 bg-white" aria-hidden="true">
                    <img src="{{ asset('images/onboarding/colour-plus.svg') }}" alt="">
                </span>
                <span>Custom</span>
                <input id="card-text-colour" x-ref="textColourPicker" type="color" :value="selectedTextColour"
                    @input="customTextColour = $event.target.value.toUpperCase()" @change="customTextColour = $event.target.value.toUpperCase()"
                    aria-label="Choose card text colour" aria-describedby="card-text-colour-help" :aria-invalid="!textColourReadable"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
            </label>
        </div>
    </div>
    <p id="card-text-colour-help" class="mt-2 text-sm text-stone-600" aria-live="polite">
        <span x-show="!customTextColour && cardDesign !== 'photo'">Custom text colours must pass the contrast check.</span>
        <span x-show="!customTextColour && cardDesign === 'photo'" x-cloak>A custom colour must stay readable against both your card colour and the photo overlay.</span>
        <span x-show="customTextColour && textColourReadable" x-cloak>Using your custom colour.</span>
        <span x-show="!textColourReadable" x-cloak class="font-medium text-red-700">This colour is hard to read. Choose a different colour or use automatic.</span>
    </p>
    <x-input-error :messages="$errors->get('card_text_color')" class="mt-2" />
    <x-stamp-colour :model="$model" />
</div>
