<x-email-layout :title="'Welcome to '.$store->name" :action-url="$verificationUrl" action-label="Verify email and view card" footnote="This verification link expires in 24 hours. If you didn't join this loyalty programme, you can ignore this email.">
    <p style="margin:0 0 16px;">Hello {{ $customer->name ?? 'there' }}, your loyalty card is ready. Start earning stamps with each visit and track your progress towards your next reward.</p>
    <p style="margin:0;">You can collect stamps now. Verify your email before redeeming rewards. Your cards and rewards at other stores stay separate. You can also add it to Apple Wallet or Google Wallet from your card page.</p>
</x-email-layout>
