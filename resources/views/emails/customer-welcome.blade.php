<x-email-layout :title="'Welcome to '.$store->name" :action-url="$verificationUrl" action-label="Verify email and view card" footnote="This verification link expires in 24 hours. If you didn't join this loyalty programme, you can ignore this email.">
    <p style="margin:0 0 16px;">Hello {{ $customer->name ?? 'there' }}, your loyalty card is ready. Start earning stamps with each visit and track your progress towards your next reward.</p>
    <p style="margin:0;">Verify your email to protect your card. You can also add it to Apple Wallet or Google Wallet from your card page.</p>
</x-email-layout>
