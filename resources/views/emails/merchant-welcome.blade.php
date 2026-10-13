<x-email-layout title="Welcome to Kawhe Loyalty" :action-url="$dashboardUrl" action-label="Open your dashboard">
    <p style="margin:0 0 16px;">Hello {{ $user->name }}, your account{{ $store?->name ? ' for '.$store->name : '' }} is ready.</p>
    <p style="margin:0;">Set up your loyalty card, choose your rewards, and share your QR code so customers can start collecting stamps.</p>
</x-email-layout>
