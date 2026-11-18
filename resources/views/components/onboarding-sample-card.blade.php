@props(['storeName' => 'Harbour Coffee', 'rewardTitle' => 'Free regular coffee', 'target' => 6, 'followRewardForm' => false])

<x-onboarding-phone>
    <div class="kawhe-setup-sample px-4 py-5 text-left"
        @if($followRewardForm)
            x-data="{ store: @js($storeName), reward: @js($rewardTitle), target: @js($target) }"
            @onboarding-reward-preview.window="store = $event.detail.store; reward = $event.detail.reward; target = $event.detail.target"
        @endif>
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
                <img src="{{ asset('images/kawhe-icon-white.png') }}" alt="" class="h-7 w-7 shrink-0 object-contain">
                <p class="break-words text-sm font-semibold" @if($followRewardForm) x-text="store" @endif>{{ $storeName }}</p>
            </div>
            <div class="shrink-0 text-right">
                <p class="text-[10px] font-medium uppercase">Stamps</p>
                <p class="text-sm tabular-nums">0 / <span @if($followRewardForm) x-text="target" @endif>{{ $target }}</span></p>
            </div>
        </div>
        <div class="my-6 flex flex-wrap justify-center gap-1.5" aria-hidden="true">
            @if($followRewardForm)
                <template x-for="stamp in Math.min(20, target)" :key="stamp">
                    <span x-show="target <= 20" class="size-6 rounded-full border-2 border-white/90"></span>
                </template>
                <span x-show="target > 20" class="h-3 w-full rounded-full border-2 border-white/90"></span>
            @elseif($target > 20)
                <span class="h-3 w-full rounded-full border-2 border-white/90"></span>
            @else
                @for($i = 0; $i < $target; $i++)
                    <span class="size-6 rounded-full border-2 border-white/90"></span>
                @endfor
            @endif
        </div>
        <div class="flex justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[10px] uppercase">Reward</p>
                <p class="break-words text-sm" @if($followRewardForm) x-text="reward" @endif>{{ $rewardTitle }}</p>
            </div>
            <div class="shrink-0">
                <p class="text-[10px] uppercase">Rewards ready</p><p class="text-sm">0</p>
            </div>
        </div>
        <div class="mx-auto mt-6 w-fit max-w-full rounded-md bg-white p-2 text-center text-xs text-stone-900">
            <img src="{{ asset('images/qr-preview-sample.png') }}" alt="Sample QR code, for preview only" class="mx-auto h-24 w-24">
            <p class="mt-1">Manual code: A4K9</p>
        </div>
    </div>
</x-onboarding-phone>
