<x-merchant-design-layout title="Subscription status" subtitle="The app checks Stripe before applying your subscription state.">

    <div class="w-full max-w-[720px]">
        <section class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
                    @if(isset($error))
                        <!-- Error State -->
                        <h2 class="text-xl font-semibold text-stone-900 mb-2">Issue Detected</h2>
                        <p class="text-stone-600 mb-6">{{ $error }}</p>
                    @elseif(isset($message))
                        <!-- Processing/Async Payment State -->
                        <h2 class="text-xl font-semibold text-stone-900 mb-2">
                            @if(isset($isAsyncPayment) && $isAsyncPayment)
                                Payment Processing
                            @else
                                Subscription Activating
                            @endif
                        </h2>
                        <p class="text-stone-600 mb-6">{{ $message }}</p>
                        @if(isset($canRetry) && $canRetry)
                            <div class="mb-6 p-4 bg-accent-50 border border-accent-200 rounded-lg">
                                <p class="text-sm text-accent-800 mb-3">
                                    You can manually sync your subscription status using the button below.
                                </p>
                                @if(isset($sessionId))
                                    <form method="POST" action="{{ route('billing.sync') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="session_id" value="{{ $sessionId }}">
                                        <x-ui.button type="submit" variant="primary" class="w-full max-w-[400px]">
                                            Sync Subscription Now
                                        </x-ui.button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @else
                        <!-- Success State (should redirect, but fallback) -->
                        <h2 class="text-xl font-semibold text-stone-900 mb-2">Subscription Activated!</h2>
                        <p class="text-stone-600 mb-6">
                            Your Pro plan subscription has been successfully activated. You can now create up to {{ config('billing.plans.pro.stores') }} stores and {{ config('billing.plans.pro.programs_per_store') }} loyalty cards per store.
                        </p>
                    @endif

                    @if(!empty($nextSteps))
                        <div class="mt-6 rounded-lg border border-stone-200 bg-white p-4 text-left">
                            <p class="text-sm font-semibold text-stone-800">What to do next</p>
                            <ul class="mt-2 space-y-2 text-sm leading-relaxed text-stone-600 list-disc list-inside">
                                @foreach($nextSteps as $step)
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    <div class="space-y-3">
                        <x-ui.button href="{{ route('billing.index', ['refresh' => 1]) }}" variant="primary" class="w-full max-w-[400px]">
                            Check Subscription Status
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.dashboard') }}" variant="secondary" class="w-full max-w-[400px]">
                            Go to Dashboard
                        </x-ui.button>
                    </div>
        </section>
    </div>
</x-merchant-design-layout>
