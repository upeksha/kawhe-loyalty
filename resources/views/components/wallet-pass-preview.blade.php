<div class="space-y-4" role="status" aria-live="polite">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Wallet preview</p>
            <p class="mt-1 text-sm text-stone-600">Apple Wallet-style front card preview using your live store branding.</p>
        </div>
        <span class="hidden sm:inline-flex items-center rounded-full border border-stone-200 bg-white px-3 py-1 text-xs font-medium text-stone-500">Preview approximation</span>
    </div>

    <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-lg shadow-stone-200/40">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-stone-500">Apple Wallet</p>
                <p class="mt-1 text-xs text-stone-500">Front-of-card preview</p>
            </div>
            <span class="rounded-full bg-stone-100 px-2.5 py-1 text-[11px] font-medium text-stone-500">iPhone</span>
        </div>

        <div class="mx-auto w-full max-w-[18rem] overflow-hidden rounded-[28px] border border-stone-900/10 shadow-2xl shadow-stone-300/40" :style="{ backgroundColor: bgColor || '#1F2937' }">
            <div class="px-4 pt-4 pb-3 text-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full bg-white/95 shadow-sm ring-1 ring-black/5">
                        <template x-if="passLogoPreview || logoPreview">
                            <img :src="passLogoPreview || logoPreview" alt="" class="h-full w-full object-contain p-1.5">
                        </template>
                        <template x-if="!(passLogoPreview || logoPreview)">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-700">Logo</span>
                        </template>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold uppercase tracking-[0.18em] text-white/70">Loyalty card</p>
                        <p class="truncate text-2xl font-semibold leading-none" x-text="storeName || 'Your store'"></p>
                    </div>
                </div>
            </div>

            <div class="relative h-36 overflow-hidden border-t border-white/10 border-b border-white/10">
                <div x-show="passHeroPreview" class="absolute inset-0 bg-cover bg-center" :style="{ backgroundImage: `linear-gradient(rgba(15, 23, 42, 0.18), rgba(15, 23, 42, 0.34)), url(${passHeroPreview})` }"></div>
                <div x-show="!passHeroPreview" class="absolute inset-0" :style="{ background: `linear-gradient(135deg, ${brandColor || '#0EA5E9'}33, rgba(255,255,255,0.06))` }"></div>
                <div class="absolute inset-x-0 top-6 flex items-center justify-center gap-3 px-5">
                    <template x-for="stamp in Array.from({ length: Math.max(Number(rewardTarget) || 1, 1) }, (_, index) => index + 1)" :key="`apple-stamp-${stamp}`">
                        <span class="h-9 w-9 rounded-full border-[3px] shadow-sm" :style="stamp <= Math.min(3, Math.max(Number(rewardTarget) || 1, 1)) ? { borderColor: '#FFFFFF', backgroundColor: '#FFFFFF' } : { borderColor: 'rgba(255,255,255,0.95)', backgroundColor: 'transparent' }"></span>
                    </template>
                </div>
            </div>

            <div class="space-y-5 px-4 py-4 text-white">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">Customer</p>
                    <p class="truncate pt-1 text-3xl font-medium leading-none">Alex Parker</p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-4 text-center shadow-lg shadow-black/10">
                    <div class="mx-auto h-28 w-28 rounded-lg bg-[linear-gradient(90deg,transparent_0,transparent_8%,#000_8%,#000_12%,transparent_12%,transparent_20%,#000_20%,#000_26%,transparent_26%,transparent_33%,#000_33%,#000_36%,transparent_36%,transparent_42%,#000_42%,#000_49%,transparent_49%,transparent_55%,#000_55%,#000_58%,transparent_58%,transparent_67%,#000_67%,#000_72%,transparent_72%,transparent_78%,#000_78%,#000_86%,transparent_86%,transparent_100%)]"></div>
                    <p class="pt-3 text-sm font-medium text-stone-800">Manual code: A1B2</p>
                </div>
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">Reward</p>
                        <p class="truncate pt-1 text-base font-medium" x-text="rewardTitle || 'Free coffee'"></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/60">Status</p>
                        <p class="pt-1 text-base font-semibold" x-text="`${Math.min(3, Math.max(Number(rewardTarget) || 1, 1))}/${Math.max(Number(rewardTarget) || 1, 1)} stamps`"></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
