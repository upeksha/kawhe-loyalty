@php
    $fallbackHeroUrl = asset('images/wallet-preview-hero.svg');
@endphp

<div class="space-y-3" role="status" aria-live="polite">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Wallet preview</p>
            <p class="mt-1 text-sm text-stone-600">Apple Wallet-style front card preview.</p>
        </div>
    </div>

    <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-lg shadow-stone-200/40">
        <div class="mx-auto w-full max-w-[20rem] overflow-hidden rounded-[28px] border border-stone-900/10 shadow-2xl shadow-stone-300/40" :style="{ backgroundColor: bgColor || '#1F2937' }">
            <div class="px-4 py-4 text-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-white shadow-sm ring-1 ring-black/5">
                        <template x-if="passLogoPreview || logoPreview">
                            <img :src="passLogoPreview || logoPreview" alt="" class="h-full w-full object-contain p-1.5">
                        </template>
                        <template x-if="!(passLogoPreview || logoPreview)">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-700">Logo</span>
                        </template>
                    </div>
                    <p class="min-w-0 truncate text-[2.15rem] font-semibold leading-none" x-text="storeName || 'Your store'"></p>
                </div>
            </div>

            <div class="relative h-44 overflow-hidden border-t border-white/10 border-b border-white/10">
                <img :src="passHeroPreview || @js($fallbackHeroUrl)" alt="" class="absolute inset-0 h-full w-full object-cover" />
                <div class="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(15,23,42,0.04),rgba(15,23,42,0.16))]"></div>
                <div class="absolute inset-x-0 top-9 z-10 flex items-center justify-center gap-3 px-5">
                    <template x-for="stamp in Array.from({ length: Math.max(Number(rewardTarget) || 1, 1) }, (_, index) => index + 1)" :key="`apple-stamp-${stamp}`">
                        <span class="h-10 w-10 rounded-full border-[4px] shadow-[0_1px_8px_rgba(0,0,0,0.08)]" :class="stamp <= Math.min(2, Math.max(Number(rewardTarget) || 1, 1)) ? 'bg-white border-white' : 'bg-transparent border-white/95'"></span>
                    </template>
                </div>
            </div>

            <div class="border-b border-white/10 px-4 py-4 text-white">
                <div class="grid grid-cols-2 gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/65">Customer</p>
                        <p class="truncate pt-1 text-[2.9rem] font-light leading-none">John Doe</p>
                    </div>
                    <div class="min-w-0 text-left">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/65">Status</p>
                        <p class="truncate pt-1 text-[2.35rem] font-light leading-none" x-text="`${Math.min(2, Math.max(Number(rewardTarget) || 1, 1))}/${Math.max(Number(rewardTarget) || 1, 1)} stamps`"></p>
                    </div>
                </div>
            </div>

            <div class="px-4 py-7">
                <div class="mx-auto w-full max-w-[165px] rounded-2xl bg-transparent p-3 shadow-none">
                    <img src="{{ asset('images/qr-preview-sample.svg') }}" alt="Sample QR code preview" class="mx-auto h-[80px] w-[80px] rounded-lg object-contain" />
                    <p class="pt-2.5 text-center text-[1.55rem] font-medium leading-none text-black">Manual code: J7LU</p>
                </div>
            </div>
        </div>
    </section>
</div>
