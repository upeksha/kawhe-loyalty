<x-email-layout :store="$store ?? null" :title="isset($store) ? 'Verify your email for '.$store->name : 'Verify your email address'" :action-url="$verificationUrl" action-label="Verify email address" :footnote="'This link expires in '.($linkLifetime ?? '24 hours').'. If you didn’t request this verification, you can ignore this email.'">
    <p style="margin:0;">Please verify your email address to protect your loyalty card and recover it later.</p>
</x-email-layout>
