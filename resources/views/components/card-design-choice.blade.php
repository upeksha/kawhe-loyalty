@props(['value' => 'simple', 'model' => null, 'section' => null, 'visual' => false, 'compact' => false])
@if(\App\Support\CoffeeCardSample::available())
@if($section !== 'stamp')
@if($model instanceof \App\Models\LoyaltyProgram)<input type="hidden" name="program_id" value="{{ $model->id }}">@elseif($model)<input type="hidden" name="store_id" value="{{ $model->id }}">@endif
<div @class(['space-y-5 rounded-2xl border border-stone-200 bg-white p-5' => !$compact, 'space-y-3.5' => $compact]) id="card-style-options">
    <div>
        <label for="card_design" class="block text-base font-semibold text-stone-900">Card style</label>
        @if($visual)
            <fieldset class="mt-2">
                <legend class="sr-only">Card style</legend>
                <div @class(['grid grid-cols-2 gap-2 min-[430px]:grid-cols-3', 'kawhe-editor-style-options' => $compact])>
                    @php
                        $styleChoices = ['simple' => 'Simple', 'illustrated' => 'Illustrated', 'photo' => 'Photo'];
                        if($model?->card_design === 'classic') $styleChoices['classic'] = 'Classic';
                    @endphp
                    @foreach($styleChoices as $styleKey => $styleLabel)
                        <label class="kawhe-setup-card-style cursor-pointer">
                            <input type="radio" name="preview_card_style" value="{{ $styleKey }}" :checked="cardDesign === @js($styleKey)"
                                @change="const field = $root.querySelector('#card_design'); field.value = $el.value; field.dispatchEvent(new Event('input', { bubbles: true })); field.dispatchEvent(new Event('change', { bubbles: true }));"
                                class="sr-only">
                            <span class="flex h-full flex-col gap-2 rounded-xl border border-stone-200 bg-white p-2 text-center text-sm font-semibold">
                                @if($styleKey === 'illustrated')
                                    <span class="kawhe-editor-artwork-thumb">
                                        <img src="{{ asset('images/card-patterns/organic-mask.png') }}" alt="" class="h-full w-full object-cover opacity-30">
                                    </span>
                                @elseif($styleKey === 'photo')
                                    <img src="{{ asset('images/onboarding/photo-sample.jpg') }}" alt="" class="h-7 w-full rounded object-cover">
                                @else
                                    <span class="h-7 rounded bg-brand-900"></span>
                                @endif
                                {{ $styleLabel }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
        <select id="card_design" name="card_design" x-model="cardDesign" @class(['hidden' => $visual, 'mt-2 block w-full rounded-lg border-stone-300 text-base' => !$visual])>
            @if($model?->card_design === 'classic')
                <option value="classic">Classic — uploaded Wallet banner</option>
            @endif
            <option value="simple">Simple — one colour, coffee stamps</option>
            <option value="illustrated">Illustrated — patterns and artwork</option>
            <option value="photo">Photo — your café or coffee image</option>
        </select>
    </div>
    <div x-show="cardDesign === 'illustrated'" x-cloak @class(['kawhe-editor-section' => $compact])>
        @php
            $artworkChoices = ['mountains' => 'Organic shapes', 'waves' => 'Floral leaves', 'botanical' => 'Flowing lines'];
            $artworkAssets = ['mountains' => 'organic', 'waves' => 'floral', 'botanical' => 'waves'];
            $selectedPattern = old('card_pattern', $model?->card_pattern ?? 'mountains');
        @endphp
        @if($visual)
            <fieldset>
                <legend class="text-sm font-semibold">Choose artwork</legend>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach($artworkChoices as $key => $label)
                        <label class="kawhe-setup-card-style cursor-pointer">
                            <input type="radio" name="card_pattern" value="{{ $key }}" @checked($selectedPattern === $key) class="sr-only">
                            <span class="flex h-full flex-col items-center gap-1.5 rounded-xl border border-stone-200 bg-white p-1.5 text-center text-xs font-semibold leading-tight">
                                <span class="block w-full overflow-hidden rounded bg-brand-900 text-white" :style="{backgroundColor: bgColor, color: contrastRatio(bgColor, '#FFFFFF') >= 4.5 ? '#FFFFFF' : '#111827'}">
                                    <span class="block h-8 w-full opacity-30" style="background:currentColor;mask:url('{{ asset('images/card-patterns/'.$artworkAssets[$key].'-mask.png') }}') center/cover;-webkit-mask:url('{{ asset('images/card-patterns/'.$artworkAssets[$key].'-mask.png') }}') center/cover" aria-hidden="true"></span>
                                </span>
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @else
            <label for="card_pattern" class="block text-sm font-semibold">Choose artwork</label>
            <select id="card_pattern" name="card_pattern" class="mt-2 w-full rounded-lg border-stone-300">
                @foreach($artworkChoices as $key => $label)
                    <option value="{{ $key }}" @selected($selectedPattern === $key)>{{ $label }}</option>
                @endforeach
            </select>
        @endif
        <p class="mt-2 text-sm text-stone-500">Subtle artwork uses your background colour. Preview each option before saving.</p>
        <x-input-error :messages="$errors->get('card_pattern')" class="mt-2" />
    </div>
    <div x-show="cardDesign === 'photo'" x-cloak @class(['rounded-xl border-2 border-brand-200 bg-brand-50 p-4 sm:p-5' => !$compact, 'kawhe-editor-section' => $compact])>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <label for="card_photo" class="block text-base font-semibold text-brand-900">Café or coffee photo</label>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-900">Required for Photo cards</span>
        </div>
        <p id="card-photo-guidance" class="mt-2 text-sm text-brand-900">{{ $model?->card_photo_path ? 'Your saved photo is ready. Choose a file only if you want to replace it.' : 'Choose a photo to use this card style. Add it before you continue.' }}</p>
        <div class="mt-4" x-data="{ photoName: '' }">
            <label class="flex cursor-pointer flex-wrap items-center gap-3 rounded-lg border border-brand-200 bg-white p-3 focus-within:ring-2 focus-within:ring-brand-700">
                <span class="inline-flex min-h-11 items-center rounded-lg bg-brand-700 px-4 py-3 text-sm font-semibold text-white">Choose photo</span>
                <span class="min-w-0 break-all text-sm text-stone-600" x-text="photoName || '{{ $model?->card_photo_path ? 'Saved photo will be kept' : 'No photo selected' }}'" aria-live="polite"></span>
                <input type="file" id="card_photo" name="card_photo" accept="image/png,image/jpeg,image/webp" aria-label="Café or coffee photo" aria-describedby="card-photo-guidance card-photo-formats" class="sr-only" @change="photoName = $event.target.files[0]?.name || ''" :disabled="cardDesign !== 'photo'" :required="cardDesign === 'photo' && {{ $model?->card_photo_path ? 'false' : 'true' }}">
            </label>
        </div>
        @if($model?->card_photo_path)<img src="{{ \App\Support\StoreAssets::url($model->card_photo_path) }}" alt="Saved card photo" class="mt-3 h-24 w-40 rounded-lg object-cover">@endif
        <p id="card-photo-formats" class="mt-3 text-sm text-stone-600">PNG, JPG or WebP, up to 2 MB and 3000 × 3000 pixels. We centre-crop the photo and add a dark overlay for readable stamps. Keep important details near the centre.</p>
        <x-input-error :messages="$errors->get('card_photo')" class="mt-2" />
    </div>
    <x-input-error :messages="$errors->get('card_design')" class="mt-2" />
@if($section === 'style')
</div>
@endif
@endif
@if($section !== 'style')
@if($section === 'stamp')
<div @class(['space-y-4 rounded-2xl border border-stone-200 bg-stone-50 p-4' => !$compact, 'kawhe-editor-section kawhe-editor-stamps' => $compact])>
@endif
    <div x-data="{ stampIcon: @js(old('stamp_icon', $model?->stamp_icon ?? 'coffee')) }">
        <label for="stamp_icon" class="block text-sm font-semibold">Stamp icon</label>
        @if($visual)
            <fieldset class="mt-2">
                <legend class="sr-only">Stamp icon</legend>
                <div class="grid grid-cols-3 gap-2 min-[470px]:grid-cols-5">
                    @foreach(['coffee' => 'Coffee cup', 'bean' => 'Coffee bean', 'star' => 'Star', 'heart' => 'Heart', 'custom' => 'Upload icon'] as $icon => $label)
                        <label class="kawhe-setup-icon cursor-pointer">
                            <input type="radio" name="preview_stamp_icon" class="sr-only" value="{{ $icon }}" :checked="stampIcon === @js($icon)"
                                @change="const field = document.getElementById('stamp_icon'); field.value = $el.value; field.dispatchEvent(new Event('input', { bubbles: true })); field.dispatchEvent(new Event('change', { bubbles: true }));">
                            <span class="flex h-full flex-col items-center justify-center gap-1 rounded-lg border border-stone-200 bg-white px-1 py-2 text-center text-[11px] font-semibold leading-tight">
                                <img src="{{ asset('images/onboarding/stamp-'.$icon.'.svg') }}" width="22" height="22" alt="">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
        <select id="stamp_icon" name="stamp_icon" x-model="stampIcon" @class(['hidden' => $visual, 'mt-2 w-full rounded-lg border-stone-300' => !$visual])>
            <option value="coffee">Coffee cup</option><option value="bean">Coffee bean</option><option value="star">Star</option><option value="heart">Heart</option><option value="custom">Upload my own icon</option>
        </select>
        <div x-show="stampIcon === 'custom'" class="mt-3">
            <label for="stamp_icon_upload" class="block text-sm font-semibold">Your stamp icon</label>
            <div class="mt-2" x-data="{ stampUploadName: '' }">
                <label class="flex cursor-pointer flex-wrap items-center gap-3 rounded-lg border border-brand-200 bg-white p-3 focus-within:ring-2 focus-within:ring-brand-700">
                    <span class="inline-flex min-h-11 items-center rounded-lg bg-brand-700 px-4 py-3 text-sm font-semibold text-white">Choose icon</span>
                    <span class="min-w-0 break-all text-sm text-stone-600" x-text="stampUploadName || @js($model?->stamp_icon_path ? 'Saved icon will be kept' : 'No icon selected')" aria-live="polite"></span>
                    <input id="stamp_icon_upload" name="stamp_icon_upload" type="file" accept="image/png,image/jpeg,image/webp" aria-label="Upload stamp icon" class="sr-only" @change="stampUploadName = $event.target.files[0]?.name || ''" :disabled="stampIcon !== 'custom'" :required="stampIcon === 'custom' && {{ $model?->stamp_icon_path ? 'false' : 'true' }}">
                </label>
            </div>
            @if($model?->stamp_icon_path)<img src="{{ \App\Support\StoreAssets::url($model->stamp_icon_path) }}" alt="Saved stamp icon" class="mt-2 h-16 w-16 rounded-full bg-white object-contain p-2">@endif
            <p class="mt-2 text-xs text-stone-500">Use a clear, dark icon with a transparent or white background. PNG, JPG or WebP, up to 2 MB and 3000 × 3000 pixels. It sits inside each collected stamp.</p>
        </div>
        <x-input-error :messages="$errors->get('stamp_icon')" class="mt-2" />
        <x-input-error :messages="$errors->get('stamp_icon_upload')" class="mt-2" />
    </div>
    @unless($compact)
        <x-stamp-appearance :model="$model" />
    @endunless
    @unless($compact)
        <p class="text-xs text-stone-500">Up to 20 stamps use your selected shape. Larger targets use a progress bar.</p>
    @endunless
</div>
@endif
@endif
