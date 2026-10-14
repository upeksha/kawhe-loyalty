<x-merchant-design-layout title="Account settings" subtitle="Manage your profile, password and account.">
    <div class="account-settings w-full max-w-[620px] space-y-6">
        @include('profile.partials.update-profile-information-form')

        <details id="change-password" class="space-y-6" @if($errors->updatePassword->isNotEmpty() || session('status') === 'password-updated') open @endif>
            <summary class="settings-disclosure border border-stone-200 bg-white text-brand-700">Change password</summary>
            @include('profile.partials.update-password-form')
        </details>

        <details id="delete-account" class="space-y-6" @if($errors->userDeletion->isNotEmpty()) open @endif>
            <summary class="settings-disclosure bg-red-700 text-white">Review account deletion</summary>
            @include('profile.partials.delete-user-form')
        </details>
    </div>
</x-merchant-design-layout>
