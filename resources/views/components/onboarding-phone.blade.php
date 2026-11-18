<div {{ $attributes->class('kawhe-setup-phone') }}>
    <div class="kawhe-setup-phone-screen">
        <div class="kawhe-setup-phone-status" aria-hidden="true">
            <span>9:41</span>
            <span class="kawhe-setup-phone-earpiece"></span>
            <span class="kawhe-setup-phone-indicators">
                <img src="{{ asset('images/onboarding/signal.svg') }}" alt="">
                <img src="{{ asset('images/onboarding/wifi.svg') }}" alt="">
                <img src="{{ asset('images/onboarding/battery.svg') }}" alt="">
            </span>
        </div>
        <div class="kawhe-setup-phone-content">
            {{ $slot }}
        </div>
    </div>
</div>
