<?php

namespace App\Services\Wallet;

use App\Models\AppleWalletRegistration;
use App\Models\LoyaltyProgram;
use App\Models\SupportAuditLog;
use App\Services\Wallet\Artwork\WalletArtworkService;
use App\Support\StoreAssets;

class WalletHealthService
{
    public function forProgram(LoyaltyProgram $program): array
    {
        $accountIds = $program->loyaltyAccounts()->pluck('id');
        $registrations = $accountIds->isEmpty()
            ? collect()
            : AppleWalletRegistration::query()->whereIn('loyalty_account_id', $accountIds)->active()->get();
        $logs = $accountIds->isEmpty()
            ? collect()
            : SupportAuditLog::query()
                ->where('event_type', 'wallet_sync')
                ->whereIn('loyalty_account_id', $accountIds)
                ->latest()
                ->limit(50)
                ->get();

        $manifest = $program->wallet_asset_manifest ?? [];
        $assetsCurrent = ($manifest['renderer_version'] ?? null) === WalletArtworkService::RENDERER_VERSION
            && ($manifest['design_hash'] ?? null) === $program->wallet_design_hash
            && collect(LoyaltyProgram::walletManifestPaths([
                'apple' => $manifest['apple'] ?? [],
                'google' => $manifest['google'] ?? [],
            ]))->every(fn (string $path) => StoreAssets::exists($path));

        return [
            'assets_current' => $assetsCurrent,
            'design_version' => max(1, (int) $program->wallet_design_version),
            'generated_at' => $program->wallet_assets_generated_at,
            'apple' => $this->platformHealth('apple', $logs, [
                'configured' => $this->appleConfigured(),
                'used' => $registrations->isNotEmpty(),
                'registrations' => $registrations->count(),
                'assets_current' => $assetsCurrent,
            ]),
            'google' => $this->platformHealth('google', $logs, [
                'configured' => $this->googleConfigured(),
                'used' => $logs->contains(fn ($log) => data_get($log->metadata, 'google.status') === 'success'
                    || data_get($log->metadata, 'google_status') === 'success'),
                'registrations' => null,
                'assets_current' => $assetsCurrent,
            ]),
        ];
    }

    private function platformHealth(string $platform, $logs, array $context): array
    {
        $lastSuccess = $logs->first(function ($log) use ($platform) {
            return data_get($log->metadata, "{$platform}.status") === 'success'
                || data_get($log->metadata, "{$platform}_status") === 'success';
        });
        $lastFailure = $logs->first(function ($log) use ($platform) {
            return data_get($log->metadata, "{$platform}.status") === 'failed'
                || data_get($log->metadata, "{$platform}_status") === 'failed';
        });
        $recentFailure = $lastFailure && (! $lastSuccess || $lastFailure->created_at->gt($lastSuccess->created_at));

        if (! $context['configured']) {
            [$label, $tone, $message] = ['Not configured', 'bg-stone-100 text-stone-700', 'Wallet provider credentials need to be configured by support.'];
        } elseif (! $context['assets_current']) {
            [$label, $tone, $message] = ['Processing', 'bg-blue-100 text-blue-700', 'Wallet artwork is being generated or refreshed.'];
        } elseif ($recentFailure) {
            $retryable = (bool) data_get($lastFailure->metadata, "{$platform}.retryable", false);
            [$label, $tone, $message] = $retryable
                ? ['Update delayed', 'bg-amber-100 text-amber-700', 'The wallet provider is temporarily delaying updates. A retry is safe.']
                : ['Needs attention', 'bg-red-100 text-red-700', 'A recent wallet update needs support attention.'];
        } elseif (! $context['used']) {
            [$label, $tone, $message] = ['Not yet used', 'bg-stone-100 text-stone-700', 'No customer activity has been recorded for this wallet provider yet.'];
        } else {
            [$label, $tone, $message] = ['Ready', 'bg-emerald-100 text-emerald-700', 'Wallet artwork and recent update activity look healthy.'];
        }

        return [
            'label' => $label,
            'tone' => $tone,
            'message' => $message,
            'configured' => $context['configured'],
            'registrations' => $context['registrations'],
            'last_success_at' => $lastSuccess?->created_at,
            'last_failure_at' => $lastFailure?->created_at,
            'failure_category' => data_get($lastFailure?->metadata, "{$platform}.category")
                ?? data_get($lastFailure?->metadata, "{$platform}_category"),
        ];
    }

    private function appleConfigured(): bool
    {
        return filled(config('passgenerator.pass_type_identifier'))
            && filled(config('passgenerator.team_identifier'))
            && $this->configuredFileIsReadable(config('passgenerator.certificate_store_path'));
    }

    private function googleConfigured(): bool
    {
        return filled(config('services.google_wallet.issuer_id'))
            && $this->configuredFileIsReadable(config('services.google_wallet.service_account_key'));
    }

    private function configuredFileIsReadable(?string $configuredPath): bool
    {
        if (blank($configuredPath)) {
            return false;
        }

        $pathInPrivate = preg_replace('#^storage/app/private/?#', '', $configuredPath);
        $candidates = array_unique(array_filter([
            $configuredPath,
            base_path($configuredPath),
            storage_path('app/private/'.$pathInPrivate),
        ]));

        return collect($candidates)->contains(fn (string $path) => is_file($path) && is_readable($path));
    }
}
