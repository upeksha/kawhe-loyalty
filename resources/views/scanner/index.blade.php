<x-merchant-layout>
    <x-slot name="header">
        {{ __('Scanner') }}
    </x-slot>

    @include('scanner._content')

    @push('scripts')
    @include('scanner._scripts')
    @endpush
</x-merchant-layout>
