<x-guest-layout>
    @if($signupAnalytics ?? null)
        <script id="merchant-signup-conversion">
            (() => {
                if (window.__kawheSignupTracked) return;
                window.__kawheSignupTracked = true;
                const analytics = {{ Illuminate\Support\Js::from($signupAnalytics) }};
                window.dataLayer = window.dataLayer || [];
                window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
                window.gtag('js', new Date());
                window.gtag('config', analytics.id, analytics.options);
                window.gtag('event', 'sign_up', { method: 'email', account_type: 'merchant', send_to: analytics.id });
                const script = document.createElement('script');
                script.async = true;
                script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(analytics.id);
                document.head.appendChild(script);
            })();
        </script>
    @endif
    <x-slot:merchantPreview>
        <div class="space-y-5 text-center">
            <h2 class="text-2xl font-bold">Confirm your email</h2>
            <div class="mx-auto grid size-24 place-items-center rounded-3xl bg-brand-50">
                <svg class="size-10" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/></svg>
            </div>
            <p class="text-sm">Open the link in the email to return to Kawhe.</p>
        </div>
    </x-slot:merchantPreview>
    <h1 class="kawhe-setup-title mb-2 text-2xl font-bold tracking-tight sm:text-3xl">Check your inbox</h1>
    <script>try { sessionStorage.removeItem('kawhe_merchant_signup_draft'); } catch (_) {}</script>
    <div id="verification-instructions" class="mb-4 scroll-mt-24 text-sm text-gray-600">
        {{ __('Verify your email before using your merchant dashboard, stores, or scanner. Open the verification email, or request a new link below. Check your spam folder too.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A verification email has been queued for your current email address. Check your inbox and spam folder shortly.') }}
        </div>
    @endif

    <x-input-error :messages="$errors->get('verification')" class="mb-4" />
    @if (session('status') === 'verification-link-failed')
        <p class="mb-4 text-sm text-red-600">Your account was created, but we could not queue the verification email. Please use the resend button to try again.</p>
    @endif

    <div class="mb-4 rounded-xl border border-brand-100 bg-brand-50 p-4 text-sm">
        <p class="font-semibold">Email address</p>
        <p class="mt-1 break-words text-stone-600">{{ auth()->user()->email }}</p>
    </div>
    <div class="mb-4 flex gap-4 text-sm">
        <a class="underline" href="{{ route('profile.edit') }}">Change email or manage account</a>
        @unless(auth()->user()->is_staff_account)<a class="underline" href="{{ route('billing.index') }}">Manage billing</a>@endunless
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-stone-200 pt-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
