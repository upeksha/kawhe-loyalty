<x-guest-layout>
    <article class="space-y-6 text-sm leading-6 text-stone-600">
        <header>
            <p class="font-medium text-brand-700">Kawhe Merchant · wwwDream limited</p>
            <h1 class="mt-2 text-2xl font-semibold text-stone-900">Delete your account and data</h1>
            <p class="mt-3">You can permanently delete your Kawhe account in the web app. You do not need to install or open the mobile app.</p>
        </header>

        <section aria-labelledby="deletion-steps">
            <h2 id="deletion-steps" class="text-lg font-semibold text-stone-900">How to delete your account</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li><a href="{{ route('profile.edit') }}" class="font-medium text-brand-700 underline underline-offset-4">Sign in and open Settings</a> using your Kawhe account.</li>
                <li>If you have a paid subscription, cancel it in Billing and wait until it has ended. Resolve any pending payment, invoice or checkout first. Deletion does not issue a refund.</li>
                <li>Open <strong>Review account deletion</strong> and choose <strong>Delete Account</strong>.</li>
                <li>Enter your current password and confirm. This action is permanent, so download anything you want to keep first.</li>
            </ol>
            <p class="mt-3">Cannot sign in, or want help requesting deletion? Email <a href="mailto:info@kawhe.shop?subject=Kawhe%20data%20deletion%20request" class="font-medium text-brand-700 underline underline-offset-4">info@kawhe.shop</a> from your registered address with the subject “Kawhe data deletion request”. Tell us which account or data you want removed. Do not send your password. We may need to verify ownership before acting.</p>
        </section>

        <section aria-labelledby="deleted-data">
            <h2 id="deleted-data" class="text-lg font-semibold text-stone-900">What is deleted</h2>
            <p class="mt-3">Your account, sign-in sessions and access tokens are removed. For café owners, this also removes their active and archived stores, loyalty cards, associated stamps, rewards, customer records and support records that belong only to those stores.</p>
            <p class="mt-3">Uploaded logos, photos, artwork and generated card files are queued for removal. Cleanup normally starts after about five minutes and retries if storage is temporarily unavailable.</p>
        </section>

        <section aria-labelledby="retained-data">
            <h2 id="retained-data" class="text-lg font-semibold text-stone-900">What may remain</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5">
                <li>Other cafés’ cards and records remain separate. Customer identities and artwork still needed by another café are retained. Deleting a staff account does not delete the café owner’s account.</li>
                <li>Local rotating backups are kept for approximately 14 days. Off-server recovery and release backups are retained separately until purged; account deletion does not automatically erase those copies.</li>
                <li>Security logs and payment-provider records may be retained for security, legal or accounting needs. Contact us for details about retained data and deletion requests.</li>
                <li>Previously downloaded files, screenshots and passes already saved on a customer’s phone are not remotely erased.</li>
            </ul>
        </section>

        <section aria-labelledby="archive-separate">
            <h2 id="archive-separate" class="text-lg font-semibold text-stone-900">Archiving is different</h2>
            <p class="mt-3">Archive pauses a store or card while keeping its data and images for later restoration. It does not delete your account. Permanent account deletion also removes the stores and cards you previously archived.</p>
        </section>

        <p class="border-t border-stone-200 pt-5">If you are a loyalty-card customer, contact your café or <a href="mailto:info@kawhe.shop" class="text-brand-700 underline underline-offset-4">info@kawhe.shop</a> to request deletion of your customer data. See our <a href="https://kawhe.shop/privacy-policy" class="text-brand-700 underline underline-offset-4">Privacy Policy</a> for more information.</p>
    </article>
</x-guest-layout>
