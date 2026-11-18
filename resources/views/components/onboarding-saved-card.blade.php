@props(['store'])

@php
    $program = $store->resolvedDefaultProgram();
    $model = $program ?? $store;
    $preview = app(\App\Services\Wallet\WalletPreviewDataFactory::class)->forStore($store);
@endphp

<form id="card-design-form" hidden aria-hidden="true">
    @csrf
    <input type="hidden" name="{{ $program ? 'program_id' : 'store_id' }}" value="{{ $model->id }}">
    <input type="hidden" name="card_design" value="{{ $model->card_design ?? 'classic' }}">
</form>
<x-wallet-pass-preview :preview-data="$preview" :source-wallet-logo="$model->pass_logo_url ?? $model->logo_url" onboarding />
