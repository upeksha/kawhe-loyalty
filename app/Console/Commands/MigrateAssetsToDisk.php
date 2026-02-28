<?php

namespace App\Console\Commands;

use App\Support\StoreAssets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateAssetsToDisk extends Command
{
    protected $signature = 'assets:migrate-to-disk {--source=public} {--target=} {--dry-run}';

    protected $description = 'Copy existing public store assets to the configured asset disk';

    public function handle(): int
    {
        $sourceName = (string) $this->option('source');
        $targetName = (string) ($this->option('target') ?: StoreAssets::diskName());
        $dryRun = (bool) $this->option('dry-run');

        $source = Storage::disk($sourceName);
        $target = Storage::disk($targetName);
        $directories = ['logos', 'pass-logos', 'pass-heroes', 'wallet/google/stamp-strips'];

        $copied = 0;
        $skipped = 0;

        foreach ($directories as $directory) {
            foreach ($source->allFiles($directory) as $file) {
                if ($target->exists($file)) {
                    $skipped++;
                    continue;
                }

                $this->line(($dryRun ? 'Would copy ' : 'Copying ') . $file);
                if (! $dryRun) {
                    $target->put($file, $source->get($file));
                }
                $copied++;
            }
        }

        $this->info("Completed. copied={$copied} skipped={$skipped} target={$targetName}");

        return self::SUCCESS;
    }
}
