@props(['value' => 'simple', 'model' => null])
@if(\App\Support\CoffeeCardSample::available())
@if($model instanceof \App\Models\LoyaltyProgram)<input type="hidden" name="program_id" value="{{ $model->id }}">@elseif($model)<input type="hidden" name="store_id" value="{{ $model->id }}">@endif
<div class="space-y-5 rounded-2xl border border-stone-200 bg-white p-5" id="card-style-options">
    <div>
        <label for="card_design" class="block text-base font-semibold text-stone-900">Card style</label>
        <select id="card_design" name="card_design" x-model="cardDesign" class="mt-2 block w-full rounded-lg border-stone-300 text-base">
            <option value="simple">Simple — one colour, coffee stamps</option>
            <option value="illustrated">Illustrated — patterns and artwork</option>
            <option value="photo">Photo — your café or coffee image</option>
        </select>
    </div>
    <p x-show="cardDesign === 'simple'" class="text-sm text-stone-600">Choose one background colour below. Stamps are circles with our fixed coffee-cup icon.</p>
    <div x-show="cardDesign === 'illustrated'" x-cloak>
        <label for="card_pattern" class="block text-sm font-semibold">Choose artwork</label>
        <select id="card_pattern" name="card_pattern" class="mt-2 w-full rounded-lg border-stone-300">
            @foreach(['mountains' => 'Mountain lines', 'waves' => 'Gentle waves', 'botanical' => 'Botanical leaves'] as $key => $label)
            <option value="{{ $key }}" @selected(old('card_pattern', $model?->card_pattern ?? 'mountains') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-2 text-sm text-stone-500">Subtle artwork uses your background colour. Preview each option before saving.</p>
    </div>
    <div x-show="cardDesign === 'photo'" x-cloak class="rounded-xl border-2 border-brand-200 bg-brand-50 p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <label for="card_photo" class="block text-base font-semibold text-brand-900">Café or coffee photo</label>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-900">Required for Photo cards</span>
        </div>
        <p id="card-photo-guidance" class="mt-2 text-sm text-brand-900">{{ $model?->card_photo_path ? 'Your saved photo is ready. Choose a file only if you want to replace it.' : 'Choose a photo to use this card style. Add it before you continue.' }}</p>
        <input type="file" id="card_photo" name="card_photo" accept="image/png,image/jpeg,image/webp" aria-describedby="card-photo-guidance card-photo-formats" class="mt-4 block min-h-11 w-full rounded-lg border border-brand-200 bg-white p-2 text-sm text-stone-700 file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-700 file:px-4 file:py-3 file:font-semibold file:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700" :disabled="cardDesign !== 'photo'" :required="cardDesign === 'photo' && {{ $model?->card_photo_path ? 'false' : 'true' }}">
        @if($model?->card_photo_path)<img src="{{ \App\Support\StoreAssets::url($model->card_photo_path) }}" alt="Saved card photo" class="mt-3 h-24 w-40 rounded-lg object-cover">@endif
        <p id="card-photo-formats" class="mt-3 text-sm text-stone-600">PNG, JPG or WebP, up to 2 MB and 3000 × 3000 pixels. We centre-crop the photo and add a dark overlay for readable stamps. Keep important details near the centre.</p>
        <x-input-error :messages="$errors->get('card_photo')" class="mt-2" />
    </div>
    <div x-show="cardDesign !== 'simple'" x-cloak x-data="{ stampIcon: @js(old('stamp_icon', $model?->stamp_icon ?? 'coffee')) }">
        <label for="stamp_icon" class="block text-sm font-semibold">Stamp icon</label>
        <select id="stamp_icon" name="stamp_icon" x-model="stampIcon" :disabled="cardDesign === 'simple'" class="mt-2 w-full rounded-lg border-stone-300">
            <option value="coffee">Coffee cup</option><option value="bean">Coffee bean</option><option value="star">Star</option><option value="heart">Heart</option><option value="custom">Upload my own icon</option>
        </select>
        <div x-show="stampIcon === 'custom'" class="mt-3">
            <label for="stamp_icon_upload" class="block text-sm font-semibold">Your stamp icon</label>
            <input id="stamp_icon_upload" name="stamp_icon_upload" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm" :disabled="cardDesign === 'simple' || stampIcon !== 'custom'" :required="cardDesign !== 'simple' && stampIcon === 'custom' && {{ $model?->stamp_icon_path ? 'false' : 'true' }}">
            @if($model?->stamp_icon_path)<img src="{{ \App\Support\StoreAssets::url($model->stamp_icon_path) }}" alt="Saved stamp icon" class="mt-2 h-16 w-16 rounded-full bg-white object-contain p-2">@endif
            <p class="mt-2 text-xs text-stone-500">Use a clear, dark icon with a transparent or white background. PNG, JPG or WebP, up to 2 MB and 3000 × 3000 pixels. It sits inside each collected stamp.</p>
        </div>
        <x-input-error :messages="$errors->get('stamp_icon')" class="mt-2" />
        <x-input-error :messages="$errors->get('stamp_icon_upload')" class="mt-2" />
    </div>
    <p class="text-xs text-stone-500">Up to 20 stamps use circles; larger targets use a progress bar. Full reward details and balances are kept on every platform.</p>
    <x-input-error :messages="$errors->get('card_design')" class="mt-2" />
</div>
@endif
