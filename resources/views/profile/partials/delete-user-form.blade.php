<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-stone-900">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-stone-600">
            {{ __('Permanently delete your account and the stores, cards and customer records that belong only to them, including archived stores and cards. Uploaded images and generated card files are queued for removal. Download anything you want to keep first.') }}
        </p>
        <p class="mt-2 text-sm text-stone-600">{{ __('To pause a store or card and keep its images and history, use Archive instead. Other cafés and their customer cards are not deleted. Existing backups are handled separately under the backup retention policy.') }}</p>
    </header>

    @unless(auth()->user()->is_staff_account)
    <p class="rounded-2xl border border-stone-200 bg-amber-50 p-6 text-sm text-stone-600">Cancel any paid subscription in Billing first and wait until it has ended. Pending payments, invoices, or checkout sessions must be resolved before deletion. Deletion does not issue a refund. Once billing is clear, deletion also closes your Stripe customer profile.</p>
    <a href="{{ route('billing.index') }}" class="underline text-sm text-brand-600">Manage billing</a>
    @endunless

    <x-ui.button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        variant="danger" class="w-full"
    >{{ __('Delete Account') }}</x-ui.button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="space-y-4 p-6" role="dialog" aria-modal="true" aria-labelledby="delete-account-title">
            @csrf
            @method('delete')

            <h2 id="delete-account-title" class="text-lg font-semibold text-stone-900">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-stone-600">
                {{ __('This permanently removes your account, including your archived stores and cards. Image cleanup normally starts within a few minutes and retries if storage is unavailable. This cannot be undone. Enter your password to confirm.') }}
            </p>

            <x-input-error :messages="$errors->userDeletion->get('billing')" class="mt-2" />

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full" autocomplete="current-password" required
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-ui.button x-on:click="$dispatch('close')" variant="secondary">
                    {{ __('Cancel') }}
                </x-ui.button>

                <x-ui.button type="submit" variant="danger">
                    {{ __('Delete Account') }}
                </x-ui.button>
            </div>
        </form>
    </x-modal>
</section>
