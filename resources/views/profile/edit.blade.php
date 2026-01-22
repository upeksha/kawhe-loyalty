<x-merchant-layout>
    <x-slot name="header">
        {{ __('Profile') }}
    </x-slot>

    <div class="max-w-2xl mx-auto space-y-6">
        <x-ui.card class="p-6">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-ui.card>

        <x-ui.card class="p-6">
            <div class="max-w-xl">
                @include('profile.partials.subscription-details')
            </div>
        </x-ui.card>

        <x-ui.card class="p-6">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </x-ui.card>

        <x-ui.card class="p-6">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </x-ui.card>
    </div>
</x-merchant-layout>
