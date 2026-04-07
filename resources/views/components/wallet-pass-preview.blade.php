@php
    $fallbackHeroUrl = asset('images/wallet-preview-hero.svg');
@endphp

<div class="space-y-3" role="status" aria-live="polite" x-data="{ previewMode: 'collecting' }">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Wallet preview</p>
            <p class="mt-1 text-sm text-stone-600">Apple Wallet-style front card preview.</p>
        </div>
        <div class="inline-flex rounded-xl border border-stone-200 bg-white p-1 shadow-sm">
            <button type="button" @click="previewMode = 'collecting'" class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors" :class="previewMode === 'collecting' ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-50'">Collecting</button>
            <button type="button" @click="previewMode = 'ready'" class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors" :class="previewMode === 'ready' ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-50'">Reward ready</button>
        </div>
    </div>

    <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-lg shadow-stone-200/40">
        <div class="mx-auto w-full max-w-[21rem] overflow-hidden rounded-[28px] border border-stone-900/10 bg-white shadow-2xl shadow-stone-300/30">
            <div class="relative px-5 py-5" :style="{ backgroundColor: bgColor || '#1F2937' }">
                <div class="absolute inset-x-0 top-0 h-24 opacity-20">
                    <img :src="passHeroPreview || @js($fallbackHeroUrl)" alt="" class="h-full w-full object-cover" />
                </div>
                <div class="relative flex items-center gap-3 text-white">
                    <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-white shadow-sm ring-1 ring-black/5">
                        <template x-if="passLogoPreview || logoPreview">
                            <img :src="passLogoPreview || logoPreview" alt="" class="h-full w-full object-contain p-1.5">
                        </template>
                        <template x-if="!(passLogoPreview || logoPreview)">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-700">Logo</span>
                        </template>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-lg font-semibold leading-tight" x-text="storeName || 'Your store'"></p>
                        <p class="mt-1 text-xs uppercase tracking-[0.18em] text-white/70">Loyalty card</p>
                    </div>
                </div>
            </div>

            <div class="px-5 py-5" :style="{ backgroundColor: bgColor || '#1F2937' }">
                <div class="rounded-[24px] border border-white/12 bg-white/[0.08] p-4 text-white backdrop-blur-[2px]">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/60">Customer</p>
                            <p class="mt-1 truncate text-xl font-medium">John Doe</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/60">Reward</p>
                            <p class="mt-1 text-sm font-medium" x-text="rewardTitle || 'Free coffee'"></p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/60">Progress</p>
                        <p class="text-sm font-medium" x-text="previewMode === 'ready' ? 'Reward ready' : `${Math.min(2, Math.max(Number(rewardTarget) || 1, 1))} of ${Math.max(Number(rewardTarget) || 1, 1)} stamps`"></p>
                    </div>

                    <div class="mt-3 grid grid-cols-5 gap-2">
                        <template x-for="stamp in Array.from({ length: Math.min(Math.max(Number(rewardTarget) || 1, 1), 10) }, (_, index) => index + 1)" :key="`preview-stamp-${stamp}`">
                            <span class="flex h-9 items-center justify-center rounded-full border text-xs font-semibold" :class="previewMode === 'ready' || stamp <= Math.min(2, Math.max(Number(rewardTarget) || 1, 1)) ? 'border-white bg-white text-stone-900' : 'border-white/70 text-white/80'">
                                <span x-text="stamp"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <div class="border-t border-stone-200 bg-stone-50 px-5 py-5">
                <div class="mx-auto max-w-[12rem] rounded-[24px] border border-stone-200 bg-white p-4 shadow-sm shadow-stone-200/60">
                    <img src="{{ asset('images/qr-preview-sample.svg') }}" alt="Sample QR code preview" class="mx-auto h-28 w-28 object-contain" />
                    <p class="mt-3 text-center text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Scan to collect</p>
                    <p class="mt-1 text-center text-sm font-semibold text-stone-900">Manual code: J7LU</p>
                </div>
            </div>
        </div>
    </section>
</div>
