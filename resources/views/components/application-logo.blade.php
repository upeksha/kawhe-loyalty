{{-- Kawhe App Logo --}}
{{-- Option 1: Use an image file (recommended) --}}
{{-- Place your logo file in public/images/logo.png or public/logo.png --}}
@if(file_exists(public_path('images/logo.png')) || file_exists(public_path('logo.png')))
    <img 
        src="{{ asset(file_exists(public_path('images/logo.png')) ? 'images/logo.png' : 'logo.png') }}" 
        alt="Kawhe Logo" 
        {{ $attributes->merge(['class' => 'h-8 w-auto']) }}
    />
@elseif(file_exists(public_path('images/logo.svg')) || file_exists(public_path('logo.svg')))
    <img 
        src="{{ asset(file_exists(public_path('images/logo.svg')) ? 'images/logo.svg' : 'logo.svg') }}" 
        alt="Kawhe Logo" 
        {{ $attributes->merge(['class' => 'h-8 w-auto']) }}
    />
@else
    {{-- Option 2: Simple text logo as fallback --}}
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center font-bold text-brand-600']) }}>
        <span class="text-xl">K</span>
    </div>
@endif
