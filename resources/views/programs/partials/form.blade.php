@php
    $program = $program ?? null;
    $isEdit = $program !== null;
    $formConfig = session()->hasOldInput()
        ? \App\Support\RegistrationFormConfig::fromRequest(new \Illuminate\Http\Request(session()->getOldInput()))
        : ($program?->registration_form_config ?? \App\Models\Store::defaultRegistrationFormConfig());
@endphp

<div
    class="kawhe-card-editor space-y-6"
    x-data="{
            cardDesign: @js(\App\Support\CardDesign::formStyle($program)),
        brandColor: @js(old('brand_color', $program?->brand_color ?? $store->brand_color ?? '#0EA5E9')),
        bgColor: @js(old('background_color', $program?->background_color ?? $store->background_color ?? '#1F2937')),
        logoPreview: @js($program?->logo_url ?? $store->logo_url),
        passLogoPreview: @js($program?->pass_logo_url ?? $store->pass_logo_url),
        passHeroPreview: @js($program?->pass_hero_image_url ?? $store->pass_hero_image_url),
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
            return this.cardDesign === 'classic' && this.contrastRatio(this.brandColor, this.bgColor) < 3;
        },
        get hasBlockedContrast() {
            return this.cardDesign === 'classic' && this.contrastRatio(this.brandColor, this.bgColor) < 2;
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

    <div class="kawhe-setup-grid">
        <form id="loyalty-card-form" method="POST" action="{{ $action }}" enctype="multipart/form-data" class="kawhe-setup-panel min-w-0 space-y-4"
            x-init="
            $refs.brandColor && $refs.brandColor.addEventListener('input', e => { brandColor = e.target.value; $refs.brandColorText && ($refs.brandColorText.value = e.target.value); });
            $refs.brandColorText && $refs.brandColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { brandColor = e.target.value; if ($refs.brandColor) { $refs.brandColor.value = e.target.value; $refs.brandColor.dispatchEvent(new Event('input', { bubbles: true })); } } });
            $refs.bgColor && $refs.bgColor.addEventListener('input', e => { bgColor = e.target.value; $refs.bgColorText && ($refs.bgColorText.value = e.target.value); });
            $refs.bgColorText && $refs.bgColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { bgColor = e.target.value; if ($refs.bgColor) { $refs.bgColor.value = e.target.value; $refs.bgColor.dispatchEvent(new Event('input', { bubbles: true })); } } });
            $refs.logoInput && $refs.logoInput.addEventListener('change', e => { const f = e.target.files[0]; if (f) logoPreview = URL.createObjectURL(f); });
            $refs.passLogoInput && $refs.passLogoInput.addEventListener('change', e => { const f = e.target.files[0]; if (f) passLogoPreview = URL.createObjectURL(f); });
            $refs.passHeroInput && $refs.passHeroInput.addEventListener('change', e => { const f = e.target.files[0]; if (f) passHeroPreview = URL.createObjectURL(f); });
            ">
            @csrf
            <h2 class="kawhe-setup-title text-2xl font-bold">{{ $isEdit ? 'Edit card appearance' : 'Design your loyalty card' }}</h2>
            <a href="#loyalty-card-preview" class="text-sm font-semibold text-brand-700 underline min-[900px]:hidden">See card preview ↓</a>
            <div class="space-y-3.5">
                <x-card-appearance-fields :model="$program" :brand-color="old('brand_color', $program?->brand_color ?? $store->brand_color)" :bg-color="old('background_color', $program?->background_color ?? $store->background_color)" />
            </div>
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
            <p x-show="hasBlockedContrast" x-cloak role="status" class="text-sm text-red-700">Choose colours with more contrast to enable saving.</p>
            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary" :loading-text="$isEdit ? 'Saving…' : 'Creating card…'" x-bind:disabled="hasBlockedContrast" x-bind:aria-disabled="hasBlockedContrast ? 'true' : 'false'">{{ $isEdit ? 'Save loyalty card' : 'Create loyalty card' }}</x-ui.button>
                <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="secondary">Back to cards</x-ui.button>
                @if($isEdit)
                    <x-ui.button href="{{ route('merchant.stores.programs.qr', [$store, $program]) }}" variant="ghost">View QR</x-ui.button>
                @endif
            </div>
        </form>

        <aside class="kawhe-setup-preview kawhe-setup-preview--sticky min-w-0">
          <div class="w-full min-w-0 space-y-5">
            <section id="loyalty-card-preview" class="space-y-3 text-center scroll-mt-6">
                <h2 class="text-[28px] font-bold leading-tight">Card preview</h2>
                <x-wallet-pass-preview :preview-data="$walletPreview ?? []" :source-wallet-logo="$program?->pass_logo_url ?? $store->pass_logo_url" onboarding />
                <p class="mx-auto max-w-[23rem] text-sm leading-tight">Sample Wallet pass. QR and manual code are for preview only.</p>
            </section>

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
        </aside>
    </div>
</div>

<dialog id="unsaved-card-dialog" aria-labelledby="unsaved-card-title" class="w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 text-stone-900 shadow-xl">
    <h2 id="unsaved-card-title" class="text-xl font-semibold">Leave without saving?</h2>
    <p class="mt-3 text-sm text-stone-600">Your card has unsaved changes. Keep editing to save them, or leave to discard them.</p>
    <div class="mt-5 flex flex-wrap gap-3">
        <x-ui.button id="keep-editing-card" variant="primary" autofocus>Keep editing</x-ui.button>
        <x-ui.button id="leave-card-page" variant="secondary">Leave without saving</x-ui.button>
    </div>
</dialog>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('loyalty-card-form');
        if (!form) return;
        const snapshot = () => JSON.stringify(Array.from(new FormData(form).entries())
            .filter(([name]) => !['_token', '_method'].includes(name))
            .map(([name, value]) => [name, value instanceof File ? (value.name ? [value.name, value.size, value.lastModified] : null) : value]));
        const initialState = snapshot();
        let submitted = false;
        const hasChanges = () => !submitted && snapshot() !== initialState;
        const leaveDialog = document.getElementById('unsaved-card-dialog');
        let pendingDestination = null;
        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const link = event.target.closest('a[href]');
            if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
            const destination = new URL(link.href, window.location.href);
            if (!['http:', 'https:'].includes(destination.protocol)) return;
            if (destination.origin === location.origin && destination.pathname === location.pathname && destination.search === location.search) return;
            if (!hasChanges()) return;
            event.preventDefault();
            pendingDestination = destination.href;
            if (!leaveDialog.open) leaveDialog.showModal();
        });
        document.getElementById('keep-editing-card').addEventListener('click', () => leaveDialog.close());
        leaveDialog.addEventListener('close', () => { pendingDestination = null; });
        document.getElementById('leave-card-page').addEventListener('click', () => {
            if (!pendingDestination) return;
            submitted = true;
            window.location.assign(pendingDestination);
        });
        const reveal = (field) => {
            let section = field.closest('details');
            while (section) {
                section.open = true;
                section = section.parentElement.closest('details');
            }
        };
        form.addEventListener('change', (event) => {
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
        form.addEventListener('invalid', (event) => reveal(event.target), true);
        form.addEventListener('submit', () => { submitted = true; });
        window.addEventListener('beforeunload', (event) => {
            if (!hasChanges()) return;
            event.preventDefault();
            event.returnValue = '';
        });
    });
</script>
@endpush
