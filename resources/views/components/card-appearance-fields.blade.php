@props(['model' => null, 'requireLogos' => false, 'brandColor' => null, 'bgColor' => null])
@php
    $brandColor = old('brand_color', $brandColor ?? $model?->brand_color ?? '#0EA5E9');
    $bgColor = old('background_color', $bgColor ?? $model?->background_color ?? '#1F2937');
@endphp
                <x-card-design-choice :model="$model" section="style" visual compact />

                <section class="kawhe-editor-section">
                    <div x-show="cardDesign === 'classic'" x-cloak class="mb-3">
                        <label for="brand_color" class="block text-sm font-semibold">Accent colour</label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input type="color" id="brand_color" name="brand_color" x-ref="brandColor" value="{{ $brandColor }}" class="color-swatch-input size-8 cursor-pointer rounded-full border border-stone-300 p-0">
                            <input type="text" id="brand_color_text" :disabled="cardDesign !== 'classic'" x-ref="brandColorText" value="{{ $brandColor }}" aria-label="Accent colour hex code" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" class="rounded-lg border-stone-300 px-2 py-1 text-sm">
                        </div>
                        <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <label for="background_color_text" class="text-[15px] font-semibold">Card colour</label>
                        <input type="text" id="background_color_text" x-ref="bgColorText" value="{{ $bgColor }}" aria-label="Background colour hex code" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" title="Enter a colour code such as #263E30" autocapitalize="off" spellcheck="false" class="w-20 rounded border-0 bg-transparent p-0 text-right text-xs font-semibold uppercase text-stone-600 focus:ring-brand-500">
                    </div>
                    <x-card-colour-presets visual compact :background="$bgColor" />
                    <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                    <div x-show="hasBlockedContrast" x-cloak>
                        <x-ui.alert variant="danger">
                            <p class="font-semibold">Choose more distinct colours</p>
                            <p class="mt-1 leading-relaxed">
                                Your brand and background colors are too close together, so the join page and saved card can become unreadable. Use a darker background, a brighter accent, or increase the contrast between them before continuing.
                            </p>
                        </x-ui.alert>
                    </div>
                    <div x-show="cardDesign === 'classic' && !hasBlockedContrast && (hasLowContrastPreview || hasVeryLightBackground)" x-cloak>
                        <x-ui.alert variant="warning">
                            <p class="font-semibold">Check your colours</p>
                            <p class="mt-1 leading-relaxed">
                                This combination will save, but it may still look washed out on the join page or wallet handoff. The safest fix is a darker background and a more distinct accent color.
                            </p>
                        </x-ui.alert>
                    </div>
                </section>

                <x-card-design-choice :model="$model" section="stamp" visual compact />
                <x-stamp-appearance :model="$model" compact />
                <x-card-text-colour :model="$model" compact />
                <fieldset>
                    <legend class="sr-only">Card logos for Apple and Google Wallet</legend>
                    @php
                        $uploads = [
                            ['id' => 'logo', 'label' => 'Store logo', 'ref' => 'logoInput', 'preview' => 'logoPreview', 'path' => 'logo_path'],
                            ['id' => 'pass_logo', 'label' => 'Wallet logo', 'ref' => 'passLogoInput', 'preview' => 'passLogoPreview', 'path' => 'pass_logo_path'],
                            ['id' => 'pass_hero_image', 'label' => 'Wallet banner', 'ref' => 'passHeroInput', 'preview' => 'passHeroPreview', 'path' => 'pass_hero_image_path'],
                        ];
                    @endphp
                    <div class="grid gap-2.5 min-[480px]:grid-cols-2">
                        @foreach($uploads as $upload)
                            @php($classicOnly = $upload['id'] === 'pass_hero_image')
                            <div class="min-w-0" @if($classicOnly) x-show="cardDesign === 'classic'" x-cloak @endif>
                                <label for="{{ $upload['id'] }}" class="mb-1 block text-sm font-semibold">{{ $upload['label'] }}@if($requireLogos) (required)@endif</label>
                                <label class="kawhe-editor-upload focus-within:ring-2 focus-within:ring-brand-600">
                                    <span class="min-w-0 truncate" x-text="$refs.{{ $upload['ref'] }}?.files[0]?.name || ({{ $upload['preview'] }} ? @js($upload['label'].' added') : 'No image selected')"></span>
                                    <span class="shrink-0 text-[13px] font-semibold text-brand-800" aria-hidden="true" x-text="{{ $upload['preview'] }} ? 'Replace' : 'Choose'"></span>
                                    <input type="file" id="{{ $upload['id'] }}" name="{{ $upload['id'] }}" x-ref="{{ $upload['ref'] }}"
                                        :required="{{ $requireLogos && blank($model?->{$upload['path']}) ? 'true' : 'false' }} && ({{ $classicOnly ? "cardDesign === 'classic'" : 'true' }})"
                                        @if($classicOnly) :disabled="cardDesign !== 'classic'" @endif
                                        accept="image/png,image/jpeg,image/webp" aria-describedby="card-upload-hint" class="sr-only">
                                </label>
                                <x-input-error :messages="$errors->get($upload['id'])" class="mt-2" />
                            </div>
                        @endforeach
                    </div>
                    <p id="card-upload-hint" class="mt-3 text-xs text-stone-600">PNG, JPG or WebP, up to 2 MB each. Saved images are kept unless replaced.</p>
                </fieldset>
