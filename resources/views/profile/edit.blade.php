<x-merchant-design-layout title="Account settings" subtitle="Manage your profile, password and account.">
    <div class="account-settings w-full max-w-[620px] space-y-6">
        @include('profile.partials.update-profile-information-form')

        <div x-data="{ panel: @js($errors->updatePassword->isNotEmpty() || session('status') === 'password-updated' ? 'password' : ($errors->userDeletion->isNotEmpty() ? 'deletion' : null)) }"
             x-effect="$refs.password.open = panel === 'password'; $refs.deletion.open = panel === 'deletion'">
            <div class="settings-actions grid gap-3 sm:grid-cols-3">
                <x-ui.button type="submit" form="profile-update" variant="primary" class="settings-action w-full">Save profile</x-ui.button>
                <button type="button" class="settings-disclosure w-full border border-stone-200 bg-white text-brand-700"
                        aria-controls="change-password" x-bind:aria-expanded="panel === 'password'"
                        x-on:click="panel = panel === 'password' ? null : 'password'">Change password</button>
                <button type="button" class="settings-disclosure w-full bg-red-700 text-white"
                        aria-controls="delete-account" x-bind:aria-expanded="panel === 'deletion'"
                        x-on:click="panel = panel === 'deletion' ? null : 'deletion'">Review account deletion</button>
            </div>

            <details id="change-password" x-ref="password" class="settings-panel" @if($errors->updatePassword->isNotEmpty() || session('status') === 'password-updated') open @endif>
                <summary class="sr-only" tabindex="-1">Change password form</summary>
                @include('profile.partials.update-password-form')
            </details>

            <details id="delete-account" x-ref="deletion" class="settings-panel" @if($errors->userDeletion->isNotEmpty()) open @endif>
                <summary class="sr-only" tabindex="-1">Account deletion details</summary>
                @include('profile.partials.delete-user-form')
            </details>
        </div>
    </div>
</x-merchant-design-layout>
