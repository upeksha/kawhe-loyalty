@php
    $fallbackHeroUrl = asset('images/wallet-preview-pattern.jpg');
    $fallbackLogoUrl = asset('images/wallet-preview-pattern.jpg');
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
        <div class="mx-auto w-full max-w-[20rem] rounded-[12px] overflow-hidden text-white shadow-[0_16px_36px_rgba(0,0,0,0.28)]" :style="{ backgroundColor: bgColor || '#562300' }">
            <div class="flex h-[72px] items-center gap-3 px-4">
                <div class="relative h-[35px] w-[35px] shrink-0 overflow-hidden rounded-full bg-white">
                    <template x-if="passLogoPreview || logoPreview">
                        <img :src="passLogoPreview || logoPreview" alt="" class="h-full w-full object-contain p-1">
                    </template>
                    <template x-if="!(passLogoPreview || logoPreview)">
                        <img src="{{ $fallbackLogoUrl }}" alt="" class="h-full w-full object-cover">
                    </template>
                </div>
                <h1 class="m-0 min-w-0 truncate text-[28px] font-[200] leading-none tracking-[0.2px]" x-text="storeName || 'Coffee card'"></h1>
            </div>

            <div
                class="relative h-[124px] overflow-hidden"
                :style="passHeroPreview
                    ? `background-image: url('${passHeroPreview}'); background-size: cover; background-position: center; background-repeat: no-repeat;`
                    : `background-image: url('{{ $fallbackHeroUrl }}'); background-size: 140px auto; background-position: center; background-repeat: repeat;`"
            >
                <div class="absolute inset-x-0 bottom-4 z-[1] flex gap-[9px] px-4">
                    <template x-for="stamp in Array.from({ length: Math.min(Math.max(Number(rewardTarget) || 1, 1), 5) }, (_, index) => index + 1)" :key="`preview-stamp-${stamp}`">
                        <span
                            class="h-[26px] w-[26px] rounded-full"
                            :class="previewMode === 'ready' || stamp <= Math.min(4, Math.max(Number(rewardTarget) || 1, 1)) ? 'bg-white' : 'border-2 border-white bg-transparent'"
                        ></span>
                    </template>
                </div>
            </div>

            <div class="flex min-h-[78px] justify-between px-[18px] pb-0 pt-[14px]">
                <div class="min-w-0">
                    <p class="m-0 text-[12px] tracking-[0.6px] text-white/90">CUSTOMER</p>
                    <p class="mt-[2px] truncate text-[18px] font-[300] leading-[1.06]">Massimo</p>
                </div>
                <div class="min-w-0 text-left">
                    <p class="m-0 text-[12px] tracking-[0.6px] text-white/90">STATUS</p>
                    <p class="mt-[2px] truncate text-[18px] font-[300] leading-[1.06]" x-text="previewMode === 'ready' ? 'Reward ready' : `${Math.min(4, Math.max(Number(rewardTarget) || 1, 1))}/${Math.max(Number(rewardTarget) || 1, 1)} stamps`"></p>
                </div>
            </div>

            <div class="pb-[18px] pt-[28px]">
                <div class="mx-auto w-[150px] rounded-[6px] bg-[#f3f3f3] px-[13px] pb-[10px] pt-[14px] text-center text-[#242424]">
                    <img src="{{ asset('images/qr-preview-sample.png') }}" alt="Sample QR code preview" class="mx-auto mb-[10px] h-[124px] w-[124px] rounded-[2px] object-contain" />
                    <p class="m-0 text-[12px]">Manual code: J7LU</p>
                </div>
            </div>
        </div>
    </section>
</div>
