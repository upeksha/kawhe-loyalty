<?php

namespace App\Console\Commands;

use App\Models\LoyaltyProgram;
use App\Support\StoreAssets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupWalletAssets extends Command
{
    protected $signature = 'wallet:cleanup-assets {--days=7 : Minimum age for unreferenced generated assets}';

    protected $description = 'Delete obsolete generated wallet artwork while retaining current and previous manifests';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->timestamp;
        $referenced = [];

        LoyaltyProgram::withTrashed()
            ->select(['id', 'wallet_asset_manifest'])
            ->chunkById(200, function ($programs) use (&$referenced) {
                foreach ($programs as $program) {
                    foreach (LoyaltyProgram::walletManifestPaths($program->wallet_asset_manifest) as $path) {
                        $referenced[$path] = true;
                    }
                }
            });

        $deleted = 0;
        foreach (StoreAssets::files('wallet/programs') as $path) {
            if (isset($referenced[$path])) {
                continue;
            }

            $lastModified = StoreAssets::lastModified($path);
            if ($lastModified === null || $lastModified > $cutoff) {
                continue;
            }

            try {
                StoreAssets::delete($path);
                $deleted++;
            } catch (\Throwable $exception) {
                Log::warning('Wallet artwork cleanup failed', [
                    'path' => $path,
                    'error_type' => class_basename($exception),
                ]);
            }
        }

        $this->info("Deleted {$deleted} obsolete wallet artwork file(s).");

        return self::SUCCESS;
    }
}
