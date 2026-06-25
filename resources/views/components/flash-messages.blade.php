@if (session()->has('success'))
    <x-ui.alert variant="success" class="mb-4">
        {{ session('success') }}
    </x-ui.alert>
@endif

@if (session()->has('error'))
    <x-ui.alert variant="danger" class="mb-4">
        {{ session('error') }}
    </x-ui.alert>
@endif
