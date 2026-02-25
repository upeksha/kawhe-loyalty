@if(file_exists(public_path('images/logo.png')) || file_exists(public_path('logo.png')))
    <img
        src="{{ asset(file_exists(public_path('images/logo.png')) ? 'images/logo.png' : 'logo.png') }}"
        alt="{{ config('app.name', 'Kawhe') }} Logo"
        {{ $attributes->merge(['class' => 'w-auto']) }}
    />
@elseif(file_exists(public_path('images/logo.svg')) || file_exists(public_path('logo.svg')))
    <img
        src="{{ asset(file_exists(public_path('images/logo.svg')) ? 'images/logo.svg' : 'logo.svg') }}"
        alt="{{ config('app.name', 'Kawhe') }} Logo"
        {{ $attributes->merge(['class' => 'w-auto']) }}
    />
@else
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center font-bold text-brand-600']) }}>
        <span class="text-2xl tracking-tight">{{ config('app.name', 'Kawhe') }}</span>
    </div>
@endif
