<x-email-layout :title="'Your loyalty card at '.$storeName" :action-url="$recoveryUrl" action-label="Open my card" footnote="This private link expires in 20 minutes and can be used once. If you didn't request it, you can ignore this email. Your card and details have not been changed.">
    <p style="margin:0 0 16px;">Use the button below to open your loyalty card or finish joining {{ $storeName }}.</p>
</x-email-layout>
