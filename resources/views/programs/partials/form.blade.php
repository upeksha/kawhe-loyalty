@php
    $program = $program ?? null;
    $isEdit = $program !== null;
    $formConfig = old('registration_form_config', $program?->registration_form_config ?? \App\Models\Store::defaultRegistrationFormConfig());
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <p class="text-sm text-stone-500">{{ $store->name }}</p>
        <h1 class="text-2xl font-bold text-stone-900">{{ $title }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ $subtitle }}</p>
        @if(!$isEdit && isset($usageStats))
            <p class="mt-3 text-xs text-stone-500">
                Plan usage: {{ $usageStats['programs_count'] }} / {{ $usageStats['limit'] }} loyalty cards.
                {{ $usageStats['is_subscribed'] ? 'Pro supports up to '.$usageStats['paid_limit'].' cards.' : 'Free includes 1 card.' }}
            </p>
        @endif
    </div>

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif

        <div class="space-y-6 rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
            <div>
                <label for="name" class="block text-sm font-medium text-stone-700 mb-1.5">Card name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $program?->name ?? $program?->reward_title ?? 'Coffee card') }}" class="w-full rounded-xl border border-stone-300 px-4 py-3 text-sm" required>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="reward_target" class="block text-sm font-medium text-stone-700 mb-1.5">Stamps needed for reward</label>
                    @if($hasIssuedCards)
                        <input type="hidden" name="reward_target" value="{{ old('reward_target', $program?->reward_target) }}">
                        <input type="number" id="reward_target" value="{{ old('reward_target', $program?->reward_target) }}" class="w-full rounded-xl border border-stone-300 bg-stone-100 px-4 py-3 text-sm text-stone-500" readonly>
                        <p class="mt-1 text-xs text-stone-500">This threshold is locked because customers already joined this loyalty card.</p>
                    @else
                        <input type="number" id="reward_target" name="reward_target" value="{{ old('reward_target', $program?->reward_target ?? 9) }}" min="1" class="w-full rounded-xl border border-stone-300 px-4 py-3 text-sm" required>
                    @endif
                    <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                </div>

                <div>
                    <label for="reward_title" class="block text-sm font-medium text-stone-700 mb-1.5">Reward title</label>
                    <input type="text" id="reward_title" name="reward_title" value="{{ old('reward_title', $program?->reward_title ?? 'Free coffee') }}" class="w-full rounded-xl border border-stone-300 px-4 py-3 text-sm" required>
                    <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="brand_color" class="block text-sm font-medium text-stone-700 mb-1.5">Brand color</label>
                    <input type="color" id="brand_color" name="brand_color" value="{{ old('brand_color', $program?->brand_color ?? $store->brand_color ?? '#0EA5E9') }}" class="h-12 w-16 rounded-full border border-stone-300">
                    <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                </div>
                <div>
                    <label for="background_color" class="block text-sm font-medium text-stone-700 mb-1.5">Background color</label>
                    <input type="color" id="background_color" name="background_color" value="{{ old('background_color', $program?->background_color ?? $store->background_color ?? '#1F2937') }}" class="h-12 w-16 rounded-full border border-stone-300">
                    <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label for="logo" class="block text-sm font-medium text-stone-700 mb-1.5">Logo</label>
                    <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.webp" class="block w-full text-sm text-stone-600">
                </div>
                <div>
                    <label for="pass_logo" class="block text-sm font-medium text-stone-700 mb-1.5">Wallet logo</label>
                    <input type="file" id="pass_logo" name="pass_logo" accept=".png,.jpg,.jpeg,.webp" class="block w-full text-sm text-stone-600">
                </div>
                <div>
                    <label for="pass_hero_image" class="block text-sm font-medium text-stone-700 mb-1.5">Wallet hero</label>
                    <input type="file" id="pass_hero_image" name="pass_hero_image" accept=".png,.jpg,.jpeg,.webp" class="block w-full text-sm text-stone-600">
                </div>
            </div>

            <div class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
                <div class="flex items-start gap-3">
                    <input type="checkbox" id="require_verification_for_redemption" name="require_verification_for_redemption" value="1" class="mt-1 rounded border-stone-300" {{ old('require_verification_for_redemption', $program?->require_verification_for_redemption ?? true) ? 'checked' : '' }}>
                    <div>
                        <label for="require_verification_for_redemption" class="block text-sm font-medium text-stone-800">Require email verification before redemption</label>
                        <p class="mt-1 text-xs text-stone-500">Leave this on for stronger fraud protection.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
                <p class="text-sm font-medium text-stone-800 mb-3">Join form fields</p>
                @foreach(['first_name' => 'First name', 'last_name' => 'Last name', 'phone' => 'Phone', 'birthday' => 'Birthday'] as $field => $label)
                    <div class="flex items-center justify-between py-2 border-b border-stone-200 last:border-b-0">
                        <span class="text-sm text-stone-700">{{ $label }}</span>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 text-xs text-stone-600">
                                <input type="checkbox" name="{{ $field }}_enabled" value="1" {{ old("{$field}_enabled", data_get($formConfig, "{$field}.enabled")) ? 'checked' : '' }}>
                                Enabled
                            </label>
                            <label class="flex items-center gap-2 text-xs text-stone-600">
                                <input type="checkbox" name="{{ $field }}_required" value="1" {{ old("{$field}_required", data_get($formConfig, "{$field}.required")) ? 'checked' : '' }}>
                                Required
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary">{{ $isEdit ? 'Save loyalty card' : 'Create loyalty card' }}</x-ui.button>
                <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="secondary">Back to cards</x-ui.button>
                @if($isEdit)
                    <x-ui.button href="{{ route('merchant.stores.programs.qr', [$store, $program]) }}" variant="ghost">View QR</x-ui.button>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-stone-200 bg-stone-50 p-6 shadow-sm h-fit">
            <h2 class="text-lg font-semibold text-stone-900">How this works</h2>
            <ul class="mt-3 space-y-2 text-sm text-stone-600">
                <li>Each loyalty card gets its own join link and QR code.</li>
                <li>Customers who join this card keep separate progress from your other cards.</li>
                <li>If customers already joined, the reward target locks to protect their existing progress.</li>
            </ul>
            @if(!$isEdit && isset($usageStats))
                <div class="mt-4 rounded-2xl border border-stone-200 bg-white p-4 text-sm text-stone-600">
                    <p class="font-semibold text-stone-800">Plan limit</p>
                    <p class="mt-1">
                        {{ $usageStats['is_subscribed'] ? 'Pro plan:' : 'Free plan:' }}
                        {{ $usageStats['limit'] }} loyalty card{{ $usageStats['limit'] === 1 ? '' : 's' }} allowed right now.
                    </p>
                </div>
            @endif
        </div>
    </form>
</div>
