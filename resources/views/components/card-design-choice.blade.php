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
    <div x-show="cardDesign === 'photo'" x-cloak>
        <label for="card_photo" class="block text-sm font-semibold">Café or coffee photo</label>
        <input type="file" id="card_photo" name="card_photo" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm" :disabled="cardDesign !== 'photo'" :required="cardDesign === 'photo' && {{ $model?->card_photo_path ? 'false' : 'true' }}">
        @if($model?->card_photo_path)<img src="{{ \App\Support\StoreAssets::url($model->card_photo_path) }}" alt="Saved card photo" class="mt-3 h-24 w-40 rounded-lg object-cover">@endif
        <p class="mt-2 text-sm text-stone-500">PNG, JPG or WebP, up to 2 MB and 3000 × 3000 pixels. We centre-crop the photo and add a dark overlay for readable stamps. Keep important details near the centre.</p>
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
