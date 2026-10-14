@props(['account'])
@php
    $theme = \App\Support\ProgramBranding::resolveFromAccount($account);
    $target = max(1, $account->reward_target);
@endphp
<aside aria-label="Customer card preview" class="w-full max-w-[320px] space-y-4 rounded-2xl p-6" style="background-color: {{ $theme->bg }}; color: {{ $theme->textOnBg }}">
    <p class="text-sm font-semibold">{{ $account->store->name }}</p>
    <p class="text-xl font-semibold">{{ $account->reward_title }}</p>
    <p class="text-[26px] font-semibold">{{ $account->stamp_count }} of {{ $target }} stamps</p>
    <p class="text-xs">{{ $account->reward_balance }} reward{{ $account->reward_balance === 1 ? '' : 's' }} available · {{ max(0, $target - $account->stamp_count) }} more stamps to your next reward</p>
    <div class="flex flex-wrap gap-2" aria-hidden="true">
        @for($i = 0; $i < min($target, 20); $i++)
            <span class="h-8 w-8 rounded-full" style="background-color: {{ $theme->brand }}; opacity: {{ $i < $account->stamp_count ? '1' : '.25' }}"></span>
        @endfor
        @if($target > 20)<span class="text-xs">+{{ $target - 20 }}</span>@endif
    </div>
    <div class="space-y-2 rounded-xl bg-white p-4 text-center text-stone-600">
        <div class="mx-auto h-[164px] w-[164px]" role="img" aria-label="Customer card QR code">{!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(164)->margin(1)->generate('LA:'.$account->public_token) !!}</div>
        <p class="text-xs">Customer card QR</p>
    </div>
    <p class="text-sm font-semibold">Manual code · {{ $account->manual_entry_code ?? '-' }}</p>
</aside>
