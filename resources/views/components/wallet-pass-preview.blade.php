@props(['previewData' => []])

@php
    $defaults = [
        'store_name' => 'Your store',
        'program_name' => 'Loyalty card',
        'reward_title' => 'Free coffee',
        'reward_target' => 8,
        'brand_color' => '#D6A24A',
        'background_color' => '#2B1E18',
        'apple_logo_url' => null,
        'apple_hero_url' => null,
        'google_logo_url' => null,
        'google_hero_url' => null,
        'example_customer' => 'Alex',
        'example_stamps' => 4,
        'example_rewards' => 0,
        'example_manual_code' => 'A4K9',
    ];
    $preview = array_merge($defaults, $previewData);
    $fallbackHeroUrl = asset('images/wallet-preview-pattern.jpg');
    $fallbackLogoUrl = asset('images/kawhe-icon-white.png');
@endphp

<div
    class="space-y-3"
    x-data="{
        platform: 'apple',
        previewMode: 'collecting',
        storeName: @js($preview['store_name']),
        programName: @js($preview['program_name']),
        rewardTitle: @js($preview['reward_title']),
        rewardTarget: {{ max(1, (int) $preview['reward_target']) }},
        brandColor: @js($preview['brand_color']),
        bgColor: @js($preview['background_color']),
        appleLogo: @js($preview['apple_logo_url']),
        appleHero: @js($preview['apple_hero_url']),
        googleLogo: @js($preview['google_logo_url']),
        googleHero: @js($preview['google_hero_url']),
        customerName: @js($preview['example_customer']),
        exampleRewards: {{ max(0, (int) $preview['example_rewards']) }},
        manualCode: @js($preview['example_manual_code']),
        temporaryUrls: {},
        get exampleStamps() {
            return Math.min(4, Math.max(0, this.rewardTarget - 1));
        },
        get statusText() {
            return this.previewMode === 'ready' ? 'Reward ready' : `${this.exampleStamps}/${this.rewardTarget} stamps`;
        },
        get contrastText() {
            const hex = (this.bgColor || '#2B1E18').replace('#', '');
            if (hex.length !== 6) return '#ffffff';
            const value = parseInt(hex, 16);
            const r = (value >> 16) & 255;
            const g = (value >> 8) & 255;
            const b = value & 255;
            return ((0.299 * r) + (0.587 * g) + (0.114 * b)) > 146 ? '#111111' : '#ffffff';
        },
        bindField(id, property, parser = value => value) {
            const field = document.getElementById(id);
            if (!field) return;
            const update = () => { this[property] = parser(field.value); };
            field.addEventListener('input', update);
            field.addEventListener('change', update);
        },
        bindFile(id, properties) {
            const field = document.getElementById(id);
            if (!field) return;
            field.addEventListener('change', event => {
                const file = event.target.files?.[0];
                if (!file) return;
                const url = URL.createObjectURL(file);
                properties.forEach(property => {
                    if (this.temporaryUrls[property]) URL.revokeObjectURL(this.temporaryUrls[property]);
                    this.temporaryUrls[property] = url;
                    this[property] = url;
                });
            });
        },
        initPreview() {
            this.bindField('name', 'programName');
            this.bindField('reward_title', 'rewardTitle');
            this.bindField('reward_target', 'rewardTarget', value => Math.max(1, Number(value) || 1));
            this.bindField('brand_color', 'brandColor');
            this.bindField('background_color', 'bgColor');
            this.bindFile('pass_logo', ['appleLogo', 'googleLogo']);
            this.bindFile('pass_hero_image', ['appleHero', 'googleHero']);
            window.addEventListener('pagehide', () => {
                [...new Set(Object.values(this.temporaryUrls))].forEach(url => URL.revokeObjectURL(url));
            }, { once: true });
        }
    }"
    x-init="initPreview()"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Wallet previews</p>
            <p class="mt-1 text-sm text-stone-600">Review each platform before saving.</p>
        </div>
        <div class="inline-flex rounded-xl border border-stone-200 bg-white p-1 shadow-sm" role="tablist" aria-label="Wallet platform preview">
            <button type="button" role="tab" id="apple-preview-tab" :aria-selected="platform === 'apple'" aria-controls="apple-preview-panel" @click="platform = 'apple'" @keydown.right.prevent="platform = 'google'; $nextTick(() => $refs.googleTab.focus())" x-ref="appleTab" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors" :class="platform === 'apple' ? 'bg-[#2b1e18] text-white' : 'text-stone-600 hover:bg-stone-50'">Apple Wallet</button>
            <button type="button" role="tab" id="google-preview-tab" :aria-selected="platform === 'google'" aria-controls="google-preview-panel" @click="platform = 'google'" @keydown.left.prevent="platform = 'apple'; $nextTick(() => $refs.appleTab.focus())" x-ref="googleTab" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors" :class="platform === 'google' ? 'bg-[#2b1e18] text-white' : 'text-stone-600 hover:bg-stone-50'">Google Wallet</button>
        </div>
    </div>

    <div class="flex justify-end">
        <div class="inline-flex gap-1 rounded-lg bg-stone-100 p-1">
            <button type="button" @click="previewMode = 'collecting'" class="rounded-md px-2.5 py-1 text-[11px] font-medium" :class="previewMode === 'collecting' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500'">Collecting</button>
            <button type="button" @click="previewMode = 'ready'" class="rounded-md px-2.5 py-1 text-[11px] font-medium" :class="previewMode === 'ready' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500'">Reward ready</button>
        </div>
    </div>

    <section id="apple-preview-panel" role="tabpanel" aria-labelledby="apple-preview-tab" x-show="platform === 'apple'" class="rounded-2xl border border-stone-200 bg-[#f5f5f7] p-4 shadow-sm">
        <div class="mx-auto w-full max-w-[20rem] overflow-hidden rounded-[13px] shadow-[0_18px_40px_rgba(43,30,24,0.22)]" :style="{ backgroundColor: bgColor, color: contrastText }">
            <div class="flex h-[64px] items-center gap-3 px-4">
                <div class="flex h-[42px] w-[136px] shrink-0 items-center justify-start overflow-hidden">
                    <img :src="appleLogo || '{{ $fallbackLogoUrl }}'" alt="Apple Wallet logo preview" class="max-h-[38px] max-w-[128px] object-contain object-left">
                </div>
                <p class="min-w-0 flex-1 truncate text-right text-sm font-semibold" x-text="storeName"></p>
            </div>

            <div class="relative h-[123px] overflow-hidden bg-stone-300">
                <img :src="appleHero || '{{ $fallbackHeroUrl }}'" alt="Apple Wallet hero preview" class="absolute inset-0 h-full w-full object-cover">
                <div class="absolute inset-x-0 bottom-4 flex flex-wrap gap-2 px-4">
                    <template x-for="stamp in Array.from({ length: Math.min(rewardTarget, 8) }, (_, index) => index + 1)" :key="`apple-stamp-${stamp}`">
                        <span class="h-6 w-6 rounded-full border-2" :style="previewMode === 'ready' || stamp <= exampleStamps ? `background:${contrastText}; border-color:${contrastText}` : `background:transparent; border-color:${contrastText}`"></span>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 px-4 pb-2 pt-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider opacity-70">Customer</p>
                    <p class="truncate text-lg font-light" x-text="customerName"></p>
                </div>
                <div class="min-w-0 text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-wider opacity-70">Status</p>
                    <p class="truncate text-lg font-light" x-text="statusText"></p>
                </div>
            </div>

            <div class="pb-5 pt-5">
                <div class="mx-auto w-[132px] rounded-md bg-white p-2.5 text-center text-stone-900">
                    <img src="{{ asset('images/qr-preview-sample.png') }}" alt="Example Apple Wallet QR code" class="mx-auto h-24 w-24 object-contain">
                    <p class="mt-1.5 text-[10px]" x-text="`Manual code: ${manualCode}`"></p>
                </div>
            </div>
        </div>
    </section>

    <section id="google-preview-panel" role="tabpanel" aria-labelledby="google-preview-tab" x-show="platform === 'google'" x-cloak class="rounded-2xl border border-stone-200 bg-[#f6f8fc] p-4 shadow-sm">
        <div class="mx-auto w-full max-w-[20rem] overflow-hidden rounded-[24px] bg-white shadow-[0_18px_40px_rgba(43,30,24,0.16)]">
            <div class="flex items-center gap-3 px-5 py-4" :style="{ backgroundColor: bgColor, color: contrastText }">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/95">
                    <img :src="googleLogo || '{{ $fallbackLogoUrl }}'" alt="Google Wallet circular-safe logo preview" class="h-full w-full object-contain p-[15%]">
                </div>
                <div class="min-w-0">
                    <p class="truncate text-xs opacity-75" x-text="storeName"></p>
                    <p class="truncate text-base font-semibold" x-text="programName"></p>
                </div>
            </div>

            <div class="relative aspect-[1032/812] overflow-hidden bg-stone-200">
                <img :src="googleHero || '{{ $fallbackHeroUrl }}'" alt="Google Wallet hero preview" class="absolute inset-0 h-full w-full object-cover">
                <div class="absolute inset-x-0 bottom-3 flex flex-wrap justify-center gap-2 px-4">
                    <template x-for="stamp in Array.from({ length: Math.min(rewardTarget, 8) }, (_, index) => index + 1)" :key="`google-stamp-${stamp}`">
                        <span class="h-7 w-7 rounded-full border-2 border-white" :style="previewMode === 'ready' || stamp <= exampleStamps ? `background:${brandColor}` : 'background:rgba(255,255,255,.2)'"></span>
                    </template>
                </div>
            </div>

            <div class="space-y-4 p-5">
                <div class="flex items-end justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-stone-500">Customer</p>
                        <p class="truncate text-lg font-semibold text-stone-900" x-text="customerName"></p>
                    </div>
                    <p class="shrink-0 text-sm font-medium text-stone-700" x-text="statusText"></p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-stone-100 px-3 py-2.5"><p class="text-[10px] uppercase tracking-wider text-stone-500">Stamps</p><p class="text-xl font-semibold text-stone-900" x-text="previewMode === 'ready' ? rewardTarget : exampleStamps"></p></div>
                    <div class="rounded-xl bg-stone-100 px-3 py-2.5"><p class="text-[10px] uppercase tracking-wider text-stone-500">Rewards</p><p class="text-xl font-semibold text-stone-900" x-text="previewMode === 'ready' ? 1 : exampleRewards"></p></div>
                </div>
                <div class="text-center">
                    <img src="{{ asset('images/qr-preview-sample.png') }}" alt="Example Google Wallet QR code" class="mx-auto h-24 w-24 object-contain">
                    <p class="mt-1 text-[10px] text-stone-500" x-text="`Manual code: ${manualCode}`"></p>
                </div>
            </div>
        </div>
    </section>

    <p class="text-xs leading-relaxed text-stone-500">
        Apple and Google control the final Wallet layout. This preview represents your branding and content, but spacing and typography may vary by device and operating-system version.
    </p>
</div>
