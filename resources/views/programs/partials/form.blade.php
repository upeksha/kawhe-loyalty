@php
    $program = $program ?? null;
    $isEdit = $program !== null;
    $formConfig = session()->hasOldInput()
        ? \App\Support\RegistrationFormConfig::fromRequest(new \Illuminate\Http\Request(session()->getOldInput()))
        : ($program?->registration_form_config ?? \App\Models\Store::defaultRegistrationFormConfig());
@endphp

<div
    class="space-y-6"
    x-data="{
            cardDesign: @js(old('card_design', $program?->card_design ?? 'classic')),
        brandColor: @js(old('brand_color', $program?->brand_color ?? $store->brand_color ?? '#0EA5E9')),
        bgColor: @js(old('background_color', $program?->background_color ?? $store->background_color ?? '#1F2937')),
        hexToRgb(hex) {
            const cleaned = (hex || '').replace('#', '');
            if (cleaned.length !== 6) return null;
            const value = parseInt(cleaned, 16);
            if (Number.isNaN(value)) return null;
            return { r: (value >> 16) & 255, g: (value >> 8) & 255, b: value & 255 };
        },
        luminance(hex) {
            const rgb = this.hexToRgb(hex);
            if (!rgb) return 0;
            const convert = (channel) => {
                const normalized = channel / 255;
                return normalized <= 0.03928
                    ? normalized / 12.92
                    : Math.pow((normalized + 0.055) / 1.055, 2.4);
            };
            return (0.2126 * convert(rgb.r)) + (0.7152 * convert(rgb.g)) + (0.0722 * convert(rgb.b));
        },
        contrastRatio(a, b) {
            const l1 = this.luminance(a);
            const l2 = this.luminance(b);
            const lighter = Math.max(l1, l2);
            const darker = Math.min(l1, l2);
            return (lighter + 0.05) / (darker + 0.05);
        },
        get hasLowContrastPreview() {
            return this.cardDesign !== 'illustrated_coffee' && this.contrastRatio(this.brandColor, this.bgColor) < 3;
        },
        get hasBlockedContrast() {
            return this.cardDesign !== 'illustrated_coffee' && this.contrastRatio(this.brandColor, this.bgColor) < 2;
        },
        get hasVeryLightBackground() {
            return this.luminance(this.bgColor) > 0.9;
        }
    }"
>
    <nav aria-label="Breadcrumb" class="mb-4 flex flex-wrap items-center gap-2 text-sm text-stone-500">
        <a href="{{ route('merchant.stores.index') }}" class="font-medium hover:text-brand-700">Stores</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('merchant.stores.programs.index', $store) }}" class="font-medium hover:text-brand-700">{{ $store->name }}</a>
        @if($isEdit)
            <span aria-hidden="true">/</span>
            <span class="text-stone-800" aria-current="page">{{ $program->name }}</span>
        @endif
    </nav>

    <div class="mb-6">
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-xl font-semibold text-stone-900">{{ $program?->name ?? $store->name }}</h2>
            @if($isEdit && $program->is_default)
                <x-ui.badge variant="success">Default card</x-ui.badge>
            @endif
        </div>
        <p class="mt-1 text-sm font-medium text-stone-500">Store: {{ $store->name }}</p>
        @if(!$isEdit && isset($usageStats))
            <p class="mt-3 text-xs text-stone-500">
                Plan usage: {{ $usageStats['stores_count'] ?? 0 }}/{{ $usageStats['stores_limit'] ?? 1 }} stores,
                {{ $usageStats['primary_store_programs_count'] ?? $usageStats['programs_count'] }}/{{ $usageStats['programs_per_store_limit'] ?? 1 }} cards on this store.
                {{ $usageStats['is_subscribed'] ? 'Pro: up to '.config('billing.plans.pro.stores').' stores and '.config('billing.plans.pro.programs_per_store').' cards per store.' : 'Free: 1 store, 1 card, 100 customers per card.' }}
            </p>
        @endif
    </div>

    @if(session('wallet_image_warnings'))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <p class="font-semibold">Artwork quality notes</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach(session('wallet_image_warnings') as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,760fr)_minmax(0,568fr)]">
        <form id="loyalty-card-form" method="POST" action="{{ $action }}" enctype="multipart/form-data" class="min-w-0 space-y-6 rounded-2xl border border-stone-200 bg-white p-4 sm:p-6">
            @csrf
            <x-card-design-choice :value="$program?->card_design ?? 'classic'" />
            @if($method !== 'POST') @method($method) @endif
            <x-form-error-summary form-id="loyalty-card-form" />

            <div>
                <h2 class="text-lg font-semibold text-stone-900">Card details</h2>
                <p class="mt-1 text-sm text-stone-600">Give this card a name customers will recognise, such as “Coffee card”.</p>
            </div>
            <div>
                <label for="name" class="block text-sm font-medium text-stone-700 mb-1.5">Card name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $program?->name ?? $program?->reward_title ?? 'Coffee card') }}" class="w-full rounded-xl border border-stone-300 px-4 py-3 text-base focus:border-brand-500 focus:ring-brand-500" autocomplete="off" maxlength="255" required>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="border-t border-stone-100 pt-5">
                <h2 class="text-lg font-semibold text-stone-900">Reward</h2>
                <p class="mt-1 text-sm text-stone-600">Set how customers earn and understand their reward.</p>
            </div>
            <div class="grid gap-4">
                <div>
                    <label for="reward_target" class="block text-sm font-medium text-stone-700 mb-1.5">Stamps needed for reward</label>
                    @if($hasIssuedCards)
                        <input type="hidden" name="reward_target" value="{{ $program?->reward_target }}">
                        <input type="number" id="reward_target" value="{{ $program?->reward_target }}" class="w-full rounded-xl border border-stone-300 bg-stone-100 px-4 py-3 text-sm text-stone-500" readonly>
                        <p class="mt-1.5 text-xs leading-5 text-stone-600">Locked to protect existing customer progress. To offer a different stamp target, create a new loyalty card.</p>
                    @else
                        <input type="number" id="reward_target" name="reward_target" value="{{ old('reward_target', $program?->reward_target ?? 9) }}" min="1" class="w-full rounded-xl border border-stone-300 px-4 py-3 text-sm" required>
                    @endif
                    <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                </div>

                <div>
                    <label for="reward_title" class="block text-sm font-medium text-stone-700 mb-1.5">What does the customer get?</label>
                    <input type="text" id="reward_title" name="reward_title" maxlength="255" placeholder="e.g. One free regular coffee" value="{{ old('reward_title', $program?->reward_title ?? 'Free coffee') }}" class="w-full rounded-xl border border-stone-300 px-4 py-3 text-sm" required>
                    <p class="mt-1.5 text-xs text-stone-500">Be specific about the reward and any size limit. Customers see this on their card.</p>
                    <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                </div>
            </div>

            <details class="rounded-xl border border-stone-200 p-4" @if(!$isEdit || $errors->hasAny(['brand_color', 'background_color', 'logo', 'pass_logo', 'pass_hero_image'])) open @endif>
                <summary class="cursor-pointer text-base font-semibold text-stone-900">Card design <span class="block mt-1 text-sm font-normal text-stone-500">Colours, logos and Wallet images</span></summary>
                <div class="mt-5 space-y-5">

            <div class="grid gap-4" x-show="cardDesign !== 'illustrated_coffee'">
                <div>
                    <label for="brand_color" class="block text-sm font-medium text-stone-700 mb-1.5">Accent colour</label>
                    <input type="color" id="brand_color" name="brand_color" x-model="brandColor" value="{{ old('brand_color', $program?->brand_color ?? $store->brand_color ?? '#0EA5E9') }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'">
                    <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                </div>
                <div>
                    <label for="background_color" class="block text-sm font-medium text-stone-700 mb-1.5">Background colour</label>
                    <input type="color" id="background_color" name="background_color" x-model="bgColor" value="{{ old('background_color', $program?->background_color ?? $store->background_color ?? '#1F2937') }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'">
                    <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                </div>
            </div>

            <div x-show="hasBlockedContrast" x-cloak class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <p class="font-semibold">Colors need more contrast</p>
                <p class="mt-1 leading-relaxed">
                    Your brand and background colors are too close together, so the join page and saved card can become unreadable. Use a darker background, a brighter accent, or increase the contrast between them before saving.
                </p>
            </div>

            <div x-show="cardDesign !== 'illustrated_coffee' && !hasBlockedContrast && (hasLowContrastPreview || hasVeryLightBackground)" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Preview warning</p>
                <p class="mt-1 leading-relaxed">
                    This combination will save, but it may still look washed out on the join page or wallet handoff. The safest fix is a darker background and a more distinct accent color.
                </p>
            </div>

            <div class="border-t border-stone-100 pt-5">
                <h2 class="text-lg font-semibold text-stone-900">Apple and Google Wallet</h2>
                <p class="mt-1 text-sm text-stone-600">Keep your current images or upload replacements. PNG, JPG or WebP, up to 2 MB each. Check the Wallet preview before saving.</p>
            </div>

            <div class="grid grid-cols-1 items-stretch gap-4">
                @php
                    $logoPreviewUrl = $isEdit ? $program?->logo_url : $store->logo_url;
                    $passLogoPreviewUrl = $isEdit ? $program?->pass_logo_url : $store->pass_logo_url;
                    $passHeroPreviewUrl = $isEdit ? $program?->pass_hero_image_url : $store->pass_hero_image_url;
                    $assetPreviewLabel = $isEdit ? 'Current' : 'From store';
                    $imageFields = [
                        ['id' => 'logo', 'label' => 'Customer card logo', 'helper' => 'Your logo on the customer join page and web card.', 'previewUrl' => $logoPreviewUrl, 'imgClass' => 'h-20 w-20 object-contain'],
                        ['id' => 'pass_logo', 'label' => 'Wallet logo', 'helper' => 'A clear logo with space around the edges. Used on saved Wallet cards and posters.', 'previewUrl' => $passLogoPreviewUrl, 'imgClass' => 'h-12 w-20 object-contain'],
                        ['id' => 'pass_hero_image', 'label' => 'Wallet banner image', 'helper' => 'A wide image for saved Wallet cards. Recommended: at least 750 × 288 px; edges may be cropped.', 'previewUrl' => $passHeroPreviewUrl, 'imgClass' => 'h-20 w-full max-w-[8rem] object-cover'],
                    ];
                @endphp

                @foreach($imageFields as $field)
                    <div x-show="cardDesign !== 'illustrated_coffee' || '{{ $field['id'] }}' !== 'pass_hero_image'">
                    <x-ui.image-upload-field
                        :id="$field['id']"
                        :helper="$field['helper']"
                        :label="$field['label']"
                        :preview-url="$field['previewUrl']"
                        :img-class="$field['imgClass']"
                        :existing-label="$assetPreviewLabel"
                    >
                        <x-input-error :messages="$errors->get($field['id'])" class="mt-2" />
                    </x-ui.image-upload-field>
                    </div>
                @endforeach
            </div>
            @if(!$isEdit && ($store->logo_path || $store->pass_logo_path || $store->pass_hero_image_path))
                <p class="text-xs text-stone-500">Uses your store images by default. Upload only if this card needs different artwork.</p>
            @endif

                </div>
            </details>

            <div class="border-t border-stone-100 pt-5">
                <h2 class="text-lg font-semibold text-stone-900">Reward protection</h2>
                <p class="mt-1 text-sm text-stone-600">Customers can collect stamps before verifying their email. Choose whether verification is needed to redeem.</p>
            </div>

            <div class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
                <div class="flex items-start gap-3">
                    <input type="hidden" name="require_verification_for_redemption" value="0">
                    <input type="checkbox" id="require_verification_for_redemption" name="require_verification_for_redemption" value="1" class="mt-1 rounded border-stone-300" {{ old('require_verification_for_redemption', $program?->require_verification_for_redemption ?? true) ? 'checked' : '' }}>
                    <div>
                        <label for="require_verification_for_redemption" class="block text-sm font-medium text-stone-800">Require email verification before redemption</label>
                        <p class="mt-1 text-xs text-stone-500">Recommended: keep this on so customers confirm their email before claiming a reward.</p>
                    </div>
                </div>
            </div>

            @php
                $registrationFormConfig = \App\Support\RegistrationFormConfig::normalize($formConfig);
            @endphp
            <details id="signup-fields" class="rounded-xl border border-stone-200 p-4" @if(!$isEdit || $errors->any()) open @endif>
                <summary class="cursor-pointer text-base font-semibold text-stone-900">Customer sign-up form <span class="block mt-1 text-sm font-normal text-stone-500">Email is always required. Extra details are your choice.</span></summary>

            <div
                class="mt-5"
                x-data="registrationFormConfigState(@js($registrationFormConfig))"
            >
                @include('programs.partials.registration-form-state')
                <p class="text-sm text-stone-600">Keep joining quick. “Optional” lets customers skip a field; “Required” means they must fill it in.</p>
                <div class="my-4 flex flex-wrap gap-2">
                    <button type="button" @click="applyPreset('fastest')" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">Email only</button>
                    <button type="button" @click="applyPreset('balanced')" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">Café essentials</button>
                    <button type="button" @click="applyPreset('marketing')" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">More customer details</button>
                </div>
                <p class="mb-4 text-xs text-stone-500">Café essentials adds optional first name and birthday. Presets update the choices below; nothing changes until you save.</p>
                <div class="flex items-center justify-between border-b border-stone-100 py-3 text-sm"><span>Email</span><span class="font-medium">Always required</span></div>
                @foreach(\App\Support\RegistrationFormConfig::fieldDefinitions() as $key => $meta)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 py-3">
                        <label for="signup-{{ $key }}" class="text-sm font-medium text-stone-700">{{ $meta['label'] }}</label>
                        <input type="hidden" name="{{ $key }}_enabled" x-bind:value="config.{{ $key }}.enabled ? '1' : '0'">
                        <input type="hidden" name="{{ $key }}_required" x-bind:value="config.{{ $key }}.enabled && config.{{ $key }}.required ? '1' : '0'">
                        <select id="signup-{{ $key }}" x-bind:value="!config.{{ $key }}.enabled ? 'off' : (config.{{ $key }}.required ? 'required' : 'optional')"
                            @change="config.{{ $key }} = { enabled: $event.target.value !== 'off', required: $event.target.value === 'required' }"
                            class="rounded-lg border-stone-300 text-sm">
                            <option value="off">Don't ask</option>
                            <option value="optional">Optional</option>
                            <option value="required">Required</option>
                        </select>
                    </div>
                @endforeach
            </div>

            </details>

            <p class="text-sm text-stone-500">Changes take effect when you save. Existing customers keep their progress.</p>
            <p x-show="hasBlockedContrast" x-cloak role="status" class="text-sm text-red-700">Open Card design and choose colours with more contrast to enable saving.</p>
            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary" :loading-text="$isEdit ? 'Saving…' : 'Creating card…'" x-bind:disabled="hasBlockedContrast" x-bind:aria-disabled="hasBlockedContrast ? 'true' : 'false'">{{ $isEdit ? 'Save loyalty card' : 'Create loyalty card' }}</x-ui.button>
                <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="secondary">Back to cards</x-ui.button>
                @if($isEdit)
                    <x-ui.button href="{{ route('merchant.stores.programs.qr', [$store, $program]) }}" variant="ghost">View QR</x-ui.button>
                @endif
            </div>
        </form>

        <div class="min-w-0 space-y-5 h-fit">
            <h2 class="text-xl font-semibold text-stone-900">Card preview</h2>
            <x-wallet-pass-preview :preview-data="$walletPreview ?? []" />

            @if($isEdit && isset($walletHealth))
                <details class="rounded-2xl border border-stone-200 bg-white p-5">
                    <summary class="cursor-pointer font-semibold text-stone-900">Wallet help</summary>
                    <div class="mt-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="wallet-health-title" class="text-base font-semibold text-stone-900">Wallet health</h2>
                            <p class="mt-1 text-xs text-stone-500">Design version {{ $walletHealth['design_version'] }} · {{ $walletHealth['assets_current'] ? 'Artwork current' : 'Artwork processing' }}</p>
                        </div>
                        <form method="POST" action="{{ route('merchant.stores.programs.refresh-wallets', [$store, $program]) }}">
                            @csrf
                            <button type="submit" data-loading-text="Queueing update…" class="min-h-11 text-xs font-semibold text-brand-700 hover:text-brand-800">Refresh saved cards</button>
                        </form>
                    </div>

                    <div class="mt-4 divide-y divide-stone-100">
                        @foreach(['apple' => 'Apple Wallet', 'google' => 'Google Wallet'] as $platform => $label)
                            @php($health = $walletHealth[$platform])
                            <div class="py-3 first:pt-0 last:pb-0">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-stone-800">{{ $label }}</p>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $health['tone'] }}">{{ $health['label'] }}</span>
                                </div>
                                <p class="mt-1 text-xs leading-relaxed text-stone-500">{{ $health['message'] }}</p>
                                @if($platform === 'apple')
                                    <p class="mt-1 text-[11px] text-stone-400">Installed registrations: {{ $health['registrations'] ?? 0 }}</p>
                                @endif
                                @if($health['last_success_at'])
                                    <p class="mt-1 text-[11px] text-stone-400">Last successful update: {{ $health['last_success_at']->format('j M Y, g:i a') }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    </div>
                </details>
            @endif

            @if($isEdit && !$program->is_default && !$program->trashed())
                <form method="POST" action="{{ route('merchant.stores.programs.destroy', [$store, $program]) }}" onsubmit="return confirm('Archive this card? New joins and scanning will pause. Customer history will remain preserved.');">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Archive card</x-ui.button>
                </form>
            @elseif($isEdit && $program->trashed())
                <form method="POST" action="{{ route('merchant.stores.programs.restore', [$store, $program->id]) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary">Restore Card</x-ui.button>
                </form>
            @endif


            <details class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
                <summary class="cursor-pointer text-base font-semibold text-stone-900">How this card works</summary>
                <ul class="mt-3 space-y-2 text-sm text-stone-600">
                    <li>Each loyalty card gets its own join link and QR code.</li>
                    <li>Customers who join this card keep separate progress from your other cards.</li>
                    <li>If customers already joined, the reward target locks to protect their existing progress.</li>
                </ul>
            </details>
            @if(!$isEdit && isset($usageStats))
                <div class="mt-4 rounded-2xl border border-stone-200 bg-white p-4 text-sm text-stone-600">
                    <p class="font-semibold text-stone-800">Plan limit</p>
                    <p class="mt-1">
                        {{ $usageStats['is_subscribed'] ? 'Pro plan:' : 'Free plan:' }}
                        {{ $usageStats['stores_limit'] ?? 1 }} store{{ ($usageStats['stores_limit'] ?? 1) === 1 ? '' : 's' }},
                        {{ $usageStats['programs_per_store_limit'] ?? 1 }} card{{ ($usageStats['programs_per_store_limit'] ?? 1) === 1 ? '' : 's' }} per store,
                        @if($usageStats['customers_per_program_limit'] ?? null)
                            {{ $usageStats['customers_per_program_limit'] }} customers per card.
                        @else
                            unlimited customers per card.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('loyalty-card-form');
        if (!form) return;
        let changed = false;
        const reveal = (field) => {
            let section = field.closest('details');
            while (section) {
                section.open = true;
                section = section.parentElement.closest('details');
            }
        };
        form.addEventListener('input', () => { changed = true; });
        form.addEventListener('change', (event) => {
            changed = true;
            const input = event.target;
            if (input.type !== 'file') return;
            const file = input.files?.[0];
            input.setCustomValidity(file && file.size > 2 * 1024 * 1024
                ? 'Choose an image smaller than 2 MB.' : '');
            if (!input.validity.valid) {
                reveal(input);
                input.reportValidity();
            }
        });
        form.querySelector('#signup-fields')?.addEventListener('click', (event) => {
            if (event.target.closest('button')) changed = true;
        });
        form.addEventListener('invalid', (event) => reveal(event.target), true);
        form.addEventListener('submit', () => { changed = false; });
        window.addEventListener('beforeunload', (event) => {
            if (!changed) return;
            event.preventDefault();
            event.returnValue = '';
        });
    });
</script>
@endpush
