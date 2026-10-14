<x-customer-layout :program="$program" :store="$store" title="Get your card">
    @include('join.partials.signup')
    <x-slot name="back">
        <a href="{{ route('join.index', ['slug' => $program->slug, 't' => $token]) }}" class="inline-flex items-center text-sm customer-muted">Back</a>
    </x-slot>
</x-customer-layout>
