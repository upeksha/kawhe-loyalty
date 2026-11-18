@props(['model' => null])

@php($savedStampColour = old('stamp_color', $model?->stamp_color) ?: '')

<div class="mt-2 border-t border-stone-200 pt-2" x-show="cardDesign !== 'classic'" x-cloak
    x-data="{
        customStampColour: @js($savedStampColour),
        get automaticStampColour() {
            return this.customTextColour && this.textColourReadable ? this.selectedTextColour : (this.cardDesign === 'photo' ? '#FFFFFF' : this.automaticTextColour);
        },
        get selectedStampColour() {
            return /^#[0-9A-Fa-f]{6}$/.test(this.customStampColour) ? this.customStampColour.toUpperCase() : this.automaticStampColour;
        },
        get stampColourReadable() {
            return !this.customStampColour || (/^#[0-9A-Fa-f]{6}$/.test(this.customStampColour)
                && this.contrastRatio(this.textBackground, this.selectedStampColour) >= 3
                && (this.cardDesign !== 'photo' || (this.contrastRatio(@js(\App\Support\CardDesign::PHOTO_TEXT_BACKGROUND), this.selectedStampColour) >= 3
                    && this.contrastRatio('#000000', this.selectedStampColour) >= 3)));
        },
    }"
    x-effect="
        $refs.stampColourPicker.setCustomValidity(stampColourReadable ? '' : 'Choose a more visible stamp colour or use automatic.');
        $dispatch('card-stamp-colour', stampColourReadable ? customStampColour : '');
    ">
    <input type="hidden" id="stamp-colour-value" name="stamp_color" :value="customStampColour" value="{{ $savedStampColour }}" :disabled="cardDesign === 'classic'">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
            <span class="size-[22px] shrink-0 rounded-full border border-stone-300" :style="{ backgroundColor: selectedStampColour }" aria-hidden="true"></span>
            <div>
                <label for="stamp-colour" class="block text-sm font-semibold text-stone-800">Stamp colour</label>
                <p class="mt-0.5 text-xs text-stone-600">
                    <span x-text="customStampColour ? 'Custom · ' + selectedStampColour : 'Automatic · matches card text'">Automatic · matches card text</span>
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" x-show="customStampColour" x-cloak @click="customStampColour = ''"
                aria-label="Use automatic stamp colour" class="min-h-10 text-sm font-semibold text-brand-700 underline underline-offset-4">Use automatic</button>
            <div class="relative inline-flex min-h-10 items-center rounded-lg border border-stone-300 bg-white px-3 text-sm font-semibold text-brand-800 focus-within:ring-2 focus-within:ring-brand-600">
                <span aria-hidden="true">Choose colour</span>
                <input id="stamp-colour" x-ref="stampColourPicker" type="color" :value="selectedStampColour" :disabled="cardDesign === 'classic'"
                    @input="customStampColour = $event.target.value.toUpperCase()" @change="customStampColour = $event.target.value.toUpperCase()"
                    aria-label="Choose stamp colour" aria-describedby="stamp-colour-help" :aria-invalid="!stampColourReadable"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
            </div>
        </div>
    </div>
    <p id="stamp-colour-help" x-show="!stampColourReadable" x-cloak aria-live="polite" class="mt-2 text-xs font-medium text-red-700">This colour is hard to see. Choose a different colour or use automatic.</p>
    <x-input-error :messages="$errors->get('stamp_color')" class="mt-2" />
</div>
