@props(['model' => null, 'compact' => false])

<div class="space-y-3"
    x-show="cardDesign !== 'classic'" x-cloak
    x-data="{ stampShape: @js(old('stamp_shape', $model?->stamp_shape ?: 'circle')), rewardIcon: @js(old('reward_icon', $model?->reward_icon ?: 'match')), rewardUploadName: '', rewardUploadPreview: @js($model?->reward_icon_path ? \App\Support\StoreAssets::url($model->reward_icon_path) : null) }">
    <section @class(['kawhe-editor-section' => $compact, 'rounded-xl border border-stone-200 bg-stone-50 p-4' => !$compact])>
        <fieldset class="min-w-0">
            <legend class="mb-1.5 text-[15px] font-semibold">Stamp shape</legend>
            <div class="grid grid-cols-3 gap-[5px]">
                @foreach(['circle' => 'Circle', 'square' => 'Square', 'rounded' => 'Rounded'] as $value => $label)
                    <label class="kawhe-setup-icon cursor-pointer">
                        <input type="radio" name="stamp_shape" value="{{ $value }}" x-model="stampShape" :disabled="cardDesign === 'classic'" class="sr-only">
                        <span class="kawhe-stamp-appearance-choice">
                            <img src="{{ asset('images/onboarding/shape-'.$value.'.svg') }}" alt="">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('stamp_shape')" class="mt-2" />
        </fieldset>
    </section>
    <section @class(['kawhe-editor-section' => $compact, 'rounded-xl border border-stone-200 bg-stone-50 p-4' => !$compact])>
        <fieldset class="min-w-0">
            <legend class="mb-1.5 text-[15px] font-semibold">Reward icon</legend>
            <div class="grid grid-cols-4 gap-[5px]">
                @foreach(['match' => 'Match stamp', 'gift' => 'Gift', 'star' => 'Star', 'custom' => 'Upload icon'] as $value => $label)
                    <label class="kawhe-setup-icon cursor-pointer">
                        <input type="radio" name="reward_icon" value="{{ $value }}" x-model="rewardIcon" :disabled="cardDesign === 'classic'" class="sr-only">
                        <span class="kawhe-stamp-appearance-choice">
                            <img src="{{ asset($value === 'custom' ? 'images/onboarding/stamp-custom.svg' : 'images/onboarding/reward-'.$value.'.svg') }}" alt="">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('reward_icon')" class="mt-2" />
        </fieldset>
    <div x-show="rewardIcon === 'custom'" x-cloak class="mt-3">
        <label for="reward_icon_upload" class="block text-sm font-semibold">Your reward icon</label>
        <div class="mt-2 flex min-w-0 items-center gap-2">
            <img x-show="rewardUploadPreview" :src="rewardUploadPreview" alt="Reward icon preview" class="size-10 shrink-0 rounded-lg border border-stone-200 bg-white object-contain p-1">
            <label class="flex min-w-0 flex-1 cursor-pointer flex-wrap items-center gap-3 rounded-lg border border-brand-200 bg-white p-3 focus-within:ring-2 focus-within:ring-brand-700">
                <span class="inline-flex min-h-11 items-center rounded-lg bg-brand-700 px-4 py-3 text-sm font-semibold text-white" x-text="rewardUploadPreview ? 'Replace icon' : 'Choose icon'" aria-hidden="true"></span>
                <span class="min-w-0 break-all text-sm text-stone-600" x-text="rewardUploadName || (rewardUploadPreview ? 'Saved reward icon' : 'No icon selected')" aria-live="polite"></span>
        <input type="file" id="reward_icon_upload" name="reward_icon_upload" aria-label="Upload reward icon" accept="image/png,image/jpeg,image/webp"
            :disabled="cardDesign === 'classic' || rewardIcon !== 'custom'"
            :required="cardDesign !== 'classic' && rewardIcon === 'custom' && {{ $model?->reward_icon_path ? 'false' : 'true' }}"
            @change="const file = $event.target.files[0]; rewardUploadName = file?.name || ''; if (rewardUploadPreview?.startsWith('blob:')) URL.revokeObjectURL(rewardUploadPreview); rewardUploadPreview = file ? URL.createObjectURL(file) : @js($model?->reward_icon_path ? \App\Support\StoreAssets::url($model->reward_icon_path) : null)"
            class="sr-only" aria-describedby="reward-icon-upload-help">
            </label>
        </div>
        <p id="reward-icon-upload-help" class="mt-2 text-xs text-stone-600">Use a clear icon. PNG, JPG or WebP, up to 2 MB and 3000 × 3000 pixels. Saved icons are kept unless replaced.</p>
        <x-input-error :messages="$errors->get('reward_icon_upload')" class="mt-2" />
    </div>
    <p class="mt-2 text-xs text-stone-600">The reward icon appears when a reward is ready.</p>
    </section>
</div>
