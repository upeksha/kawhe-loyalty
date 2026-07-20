<?php

namespace App\Services\Wallet\Artwork;

use App\Jobs\RefreshProgramWalletsJob;
use App\Models\LoyaltyProgram;
use App\Services\Support\SupportAuditService;
use App\Support\StoreAssets;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletArtworkService
{
    public const RENDERER_VERSION = 'wallet-artwork-v2';

    public function __construct(
        private readonly AppleWalletArtworkRenderer $appleRenderer,
        private readonly GoogleWalletArtworkRenderer $googleRenderer,
        private readonly WalletDesignHasher $designHasher,
        private readonly SupportAuditService $supportAuditService,
    ) {}

    public function syncForProgram(LoyaltyProgram $program, bool $queueRefresh = true): WalletArtworkResult
    {
        $program->loadMissing('store');
        [$logoContents, $logoSource] = $this->sourceContents(
            $program->pass_logo_path,
            public_path('images/kawhe-icon-white.png'),
            'wallet logo'
        );
        [$heroContents, $heroSource] = $this->sourceContents(
            $program->pass_hero_image_path,
            public_path('images/wallet-preview-pattern.jpg'),
            'wallet hero image'
        );

        $logoSourceHash = hash('sha256', $logoContents);
        $heroSourceHash = hash('sha256', $heroContents);
        $designHash = $this->designHasher->hash($program, $logoSourceHash, $heroSourceHash);
        $currentManifest = $program->wallet_asset_manifest ?? [];
        $manifestIsCurrent = ($currentManifest['renderer_version'] ?? null) === self::RENDERER_VERSION
            && ($currentManifest['design_hash'] ?? null) === $designHash
            && $this->manifestFilesExist($currentManifest);

        if ($program->wallet_design_hash === $designHash && $manifestIsCurrent) {
            return new WalletArtworkResult(false, $designHash, max(1, (int) $program->wallet_design_version), $currentManifest);
        }

        $appleImages = $this->appleRenderer->render($logoContents, $heroContents);
        $googleImages = $this->googleRenderer->render($logoContents, $heroContents);
        $shortHash = substr($designHash, 0, 16);
        $base = "wallet/programs/{$program->id}";
        $applePaths = [
            'logo' => "{$base}/apple/logo-v2-{$shortHash}.png",
            'logo_2x' => "{$base}/apple/logo-v2-{$shortHash}@2x.png",
            'logo_3x' => "{$base}/apple/logo-v2-{$shortHash}@3x.png",
            'strip' => "{$base}/apple/strip-v2-{$shortHash}.png",
            'strip_2x' => "{$base}/apple/strip-v2-{$shortHash}@2x.png",
            'strip_3x' => "{$base}/apple/strip-v2-{$shortHash}@3x.png",
        ];
        $googlePaths = [
            'program_logo' => "{$base}/google/program-logo-v2-{$shortHash}.png",
            'hero' => "{$base}/google/hero-v2-{$shortHash}.png",
        ];

        foreach ($applePaths as $key => $path) {
            StoreAssets::putImmutablePng($path, $appleImages[$key]);
        }
        foreach ($googlePaths as $key => $path) {
            StoreAssets::putImmutablePng($path, $googleImages[$key]);
        }

        $previous = $this->previousManifest($currentManifest, $program->wallet_assets_generated_at?->toIso8601String());
        $manifest = [
            'renderer_version' => self::RENDERER_VERSION,
            'source_hash' => hash('sha256', $logoSourceHash.'|'.$heroSourceHash),
            'design_hash' => $designHash,
            'sources' => [
                'logo' => $logoSource,
                'hero' => $heroSource,
            ],
            'apple' => $applePaths,
            'google' => $googlePaths,
            'previous' => $previous,
        ];

        $hadDesign = filled($program->wallet_design_hash);
        $designChanged = ! hash_equals((string) ($program->wallet_design_hash ?? ''), $designHash);
        $designVersion = max(1, (int) $program->wallet_design_version);
        if ($hadDesign && $designChanged) {
            $designVersion++;
        }

        $program->forceFill([
            'wallet_design_version' => $designVersion,
            'wallet_design_hash' => $designHash,
            'wallet_asset_manifest' => $manifest,
            'wallet_assets_generated_at' => now(),
            'wallet_branding_updated_at' => $designChanged ? now() : ($program->wallet_branding_updated_at ?? now()),
        ])->saveQuietly();

        if ($queueRefresh && $designChanged) {
            $this->queueProgramRefresh($program);
        }

        return new WalletArtworkResult($designChanged, $designHash, $designVersion, $manifest);
    }

    public function queueProgramRefresh(LoyaltyProgram $program, ?int $actorUserId = null, string $source = 'system'): void
    {
        DB::afterCommit(function () use ($program, $actorUserId, $source) {
            RefreshProgramWalletsJob::dispatch($program->id);
            $this->supportAuditService->log(
                eventType: 'program_wallet_refresh',
                status: 'queued',
                storeId: $program->store_id,
                actorUserId: $actorUserId,
                source: $source,
                message: 'Wallet branding refresh queued for this loyalty card.',
                metadata: [
                    'program_id' => $program->id,
                    'wallet_design_version' => $program->wallet_design_version,
                    'wallet_design_hash' => $program->wallet_design_hash,
                ]
            );
        });
    }

    private function sourceContents(?string $path, string $fallbackPath, string $label): array
    {
        if ($path) {
            $contents = StoreAssets::get($path);
            if ($contents !== null) {
                return [$contents, $path];
            }
        }

        $fallback = @file_get_contents($fallbackPath);
        if ($fallback === false) {
            throw new RuntimeException("No usable {$label} or fallback image was found.");
        }

        return [$fallback, 'fallback:'.basename($fallbackPath)];
    }

    private function manifestFilesExist(array $manifest): bool
    {
        $paths = LoyaltyProgram::walletManifestPaths([
            'apple' => $manifest['apple'] ?? [],
            'google' => $manifest['google'] ?? [],
        ]);

        return count($paths) === 8
            && collect($paths)->every(fn (string $path) => StoreAssets::exists($path));
    }

    private function previousManifest(array $manifest, ?string $generatedAt): ?array
    {
        if (empty($manifest['apple']) && empty($manifest['google'])) {
            return null;
        }

        return [
            'renderer_version' => $manifest['renderer_version'] ?? null,
            'design_hash' => $manifest['design_hash'] ?? null,
            'generated_at' => $generatedAt,
            'apple' => $manifest['apple'] ?? [],
            'google' => $manifest['google'] ?? [],
        ];
    }
}
