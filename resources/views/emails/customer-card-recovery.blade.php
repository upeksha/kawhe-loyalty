<x-email-layout :title="$joining ? 'Continue to '.$storeName : 'Your loyalty card at '.$storeName" :action-url="$recoveryUrl" :action-label="$joining ? 'Continue to '.$storeName : 'Open my card'" footnote="This private link expires in 20 minutes and can be used once. If you didn't request it, you can ignore this email. Your card and details have not been changed.">
    @if($joining)
        <p style="margin:0 0 16px;">Confirm your email to join {{ $storeName }} or open your existing card there.</p>
        <p style="margin:0 0 16px;">If you’re joining for the first time, we’ll create a separate loyalty card for this store after you confirm. Your cards, stamps and rewards at other cafés stay separate.</p>
    @else
        <p style="margin:0 0 16px;">Use the button below to open your loyalty card at {{ $storeName }}.</p>
    @endif
</x-email-layout>
