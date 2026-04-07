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
        <div class="mx-auto w-full max-w-[18rem] overflow-hidden rounded-[14px] border border-black/5 shadow-[0_18px_45px_rgba(15,23,42,0.18)]" :style="{ backgroundColor: bgColor || '#5b2a06' }">
            <div class="px-4 py-5 text-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-white shadow-sm ring-1 ring-black/5">
                        <template x-if="passLogoPreview || logoPreview">
                            <img :src="passLogoPreview || logoPreview" alt="" class="h-full w-full object-contain p-1">
                        </template>
                        <template x-if="!(passLogoPreview || logoPreview)">
                            <span class="text-[8px] font-semibold uppercase tracking-[0.16em] text-stone-700">Logo</span>
                        </template>
                    </div>
                    <p class="min-w-0 truncate text-base font-semibold leading-none" x-text="storeName || 'Coffee card'"></p>
                </div>
            </div>

            <div class="relative h-28 overflow-hidden">
                <img :src="passHeroPreview || @js($fallbackHeroUrl)" alt="" class="absolute inset-0 h-full w-full object-cover" />
                <div class="absolute inset-0 bg-black/20"></div>
                <div class="absolute inset-x-0 bottom-4 flex items-center gap-2 px-4">
                    <template x-for="stamp in Array.from({ length: Math.min(Math.max(Number(rewardTarget) || 1, 1), 5) }, (_, index) => index + 1)" :key="`preview-stamp-${stamp}`">
                        <span class="h-7 w-7 rounded-full border-2 border-white bg-white/20 shadow-sm" :class="previewMode === 'ready' || stamp <= Math.min(4, Math.max(Number(rewardTarget) || 1, 1)) ? 'bg-white border-white' : 'bg-white/5 border-white'"></span>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 px-4 py-3 text-white">
                <div class="min-w-0">
                    <p class="text-[10px] font-medium uppercase tracking-[0.14em] text-white/65">Customer</p>
                    <p class="truncate pt-1 text-[1.05rem] font-medium leading-none">Massimo</p>
                </div>
                <div class="min-w-0 text-left">
                    <p class="text-[10px] font-medium uppercase tracking-[0.14em] text-white/65">Status</p>
                    <p class="truncate pt-1 text-[1.05rem] font-medium leading-none" x-text="previewMode === 'ready' ? 'Reward ready' : `${Math.min(4, Math.max(Number(rewardTarget) || 1, 1))}/${Math.max(Number(rewardTarget) || 1, 1)} stamps`"></p>
                </div>
            </div>

            <div class="px-4 pb-4 pt-6">
                <div class="mx-auto w-full max-w-[8.4rem] rounded-[6px] bg-white p-3 shadow-sm">
                    <img src="{{ asset('images/qr-preview-sample.svg') }}" alt="Sample QR code preview" class="mx-auto h-[95px] w-[95px] object-contain" />
                    <p class="pt-2 text-center text-[0.72rem] font-medium leading-none text-stone-700">Manual code: J7LU</p>
                </div>
            </div>
        </div>
    </section>
</div>
