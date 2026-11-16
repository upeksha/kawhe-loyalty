<x-email-layout :title="'Welcome to '.$store->name" :store="$store" :action-url="$verificationUrl" :is-preview="$isPreview ?? false" action-label="Verify email and view card" footnote="This verification link expires in 24 hours. If you didn't join this loyalty programme, you can ignore this email.">
    <p style="margin:0 0 16px;">Kia ora {{ $customerName }},</p>
    @if($store->customer_welcome_note)
        <p style="margin:0 0 16px;">{!! nl2br(e($store->customer_welcome_note)) !!}</p>
    @else
        <p style="margin:0 0 16px;">Thanks for joining our loyalty programme. Show us your card next time you’re in and we’ll add your stamp.</p>
    @endif
    <p style="margin:0;">You can collect stamps now. Verify your email to protect your card and recover it later. @if($requiresVerification) You’ll also need to verify before redeeming rewards. @endif You can save the card to Apple Wallet or Google Wallet from your card page.</p>
</x-email-layout>
