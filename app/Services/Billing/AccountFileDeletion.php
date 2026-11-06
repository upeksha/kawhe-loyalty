<?php

namespace App\Services\Billing;

use App\Models\AccountFileCleanup;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Models\User;
use App\Support\CardDesign;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AccountFileDeletion
{
    private array $styleHashes = [];

    public function process(AccountFileCleanup $cleanup): bool
    {
        // Never run against an uncommitted deletion, including nested transactions.
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('File cleanup must run after commit.');
        }
        $lock = Cache::lock('account-file-cleanup:'.$cleanup->id, 1800);
        if (! $lock->get()) {
            return false;
        }
        try {
            $this->styleHashes = [];
            $cleanup->refresh();
            if ($cleanup->completed_at) {
                return true;
            }
            if (User::whereKey($cleanup->deleted_user_id)->exists()) {
                throw new RuntimeException('Account still exists.');
            }
            $cleanup->increment('attempts');
            $payload = $cleanup->payload;
            $references = $this->references($payload['asset_disk']);
            if (empty($payload['expanded'])) {
                $payload = $this->expand($cleanup, $references);
                // Persist exact paths before deleting anything: retries must not
                // need original images already removed by an earlier attempt.
                $cleanup->update(['payload' => $payload]);
            }
            foreach ($payload['files'] as $path) {
                $this->validateAssetPath($path);
            }
            foreach ($payload['pass_files'] as $path) {
                $this->validateRelativePath($path);
            }
            foreach ($payload['files'] as $path) {
                if (isset($references['files'][$path]) || $this->sharedStyle($path, $references['styles'])) {
                    continue;
                }
                $this->deleteFile($payload['asset_disk'], $path);
            }
            foreach ($payload['pass_files'] as $path) {
                $this->validateRelativePath($path);
                $this->deleteFile($payload['pass_disk'], $path);
            }
            $cleanup->update(['payload' => [], 'completed_at' => now(), 'last_error' => null]);

            return true;
        } catch (\Throwable $exception) {
            $cleanup->update([
                'last_error' => class_basename($exception),
                'available_at' => now()->addMinutes(min(60, 2 ** min(6, $cleanup->attempts))),
            ]);
            Log::error('Account file cleanup will retry', [
                'cleanup_id' => $cleanup->id, 'error_type' => class_basename($exception),
            ]);

            return false;
        } finally {
            $lock->release();
        }
    }

    private function expand(AccountFileCleanup $cleanup, array $references): array
    {
        $payload = $cleanup->payload;
        $disk = Storage::disk($payload['asset_disk']);
        $files = $payload['files'];
        $directories = array_map(fn ($directory) => "{$directory}/accounts/{$cleanup->deleted_user_id}", AccountDataDeletion::UPLOAD_DIRECTORIES);
        $directories[] = "wallet/card-styles-v2/accounts/{$cleanup->deleted_user_id}";
        foreach ($payload['program_ids'] as $id) {
            $directories[] = 'wallet/programs/'.(int) $id;
        }
        foreach ($directories as $directory) {
            $this->validateAssetPath($directory);
            array_push($files, ...$disk->allFiles($directory));
        }
        if ($payload['store_ids'] || $payload['account_ids']) {
            foreach ($disk->allFiles('wallet/google/stamp-strips') as $path) {
                $name = basename($path);
                $belongs = preg_match('/^store_(\d+)_/', $name, $m) && in_array((int) $m[1], $payload['store_ids'], true);
                $belongs = $belongs || (preg_match('/_account_(\d+)_/', $name, $m) && in_array((int) $m[1], $payload['account_ids'], true));
                if ($belongs) {
                    $files[] = $path;
                }
            }
        }
        // Legacy hashes may be shared; archived stores/cards still count as users.
        foreach ($payload['styles'] as $attributes) {
            if (($attributes['card_design'] ?? null) === 'illustrated_coffee') {
                continue; // built-in artwork, not a merchant upload
            }
            $hash = $this->styleHash(new Store($attributes), $payload['asset_disk']);
            if (! isset($references['styles'][$hash])) {
                array_push($files, ...$disk->allFiles('wallet/card-styles-v1/'.$hash));
            }
        }
        $passFiles = [];
        $passDisk = Storage::disk($payload['pass_disk']);
        foreach ($payload['pass_serials'] as $serial) {
            if (! preg_match('/^(?:env_[a-f0-9]+_)?kawhe-\d+(?:-\d+)?$/', $serial)) {
                throw new RuntimeException('Unexpected pass identity.');
            }
            $base = rtrim((string) $payload['pass_directory'], '/').'/'.$serial;
            $this->validateRelativePath($base);
            $passFiles[] = $base.'.pkpass';
            array_push($passFiles, ...$passDisk->allFiles($base));
        }
        $payload['files'] = array_values(array_unique($files));
        $payload['pass_files'] = array_values(array_unique($passFiles));
        $payload['expanded'] = true;
        unset($payload['styles']);

        return $payload;
    }

    private function references(string $disk): array
    {
        $references = ['files' => [], 'styles' => []];
        foreach ([Store::class, LoyaltyProgram::class] as $modelClass) {
            foreach ($modelClass::withTrashed()->cursor() as $model) {
                foreach ($model->only(AccountDataDeletion::IMAGE_FIELDS) as $path) {
                    if ($path) {
                        $references['files'][$path] = true;
                    }
                }
                if ($model instanceof LoyaltyProgram) {
                    foreach (LoyaltyProgram::walletManifestPaths($model->wallet_asset_manifest) as $path) {
                        $references['files'][$path] = true;
                    }
                }
                if ($model->card_design !== 'illustrated_coffee') {
                    $references['styles'][$this->styleHash($model, $disk)] = true;
                }
            }
        }

        return $references;
    }

    private function styleHash($model, string $disk): string
    {
        $key = hash('sha256', serialize([$disk, $model->only([
            'card_design', 'card_pattern', 'stamp_icon', 'stamp_icon_path',
            'card_photo_path', 'brand_color', 'background_color',
        ])]));
        if (isset($this->styleHashes[$key])) {
            return $this->styleHashes[$key];
        }
        $options = CardDesign::options($model, contents: false);
        $storage = Storage::disk($disk);
        if ($model->card_photo_path && $storage->exists($model->card_photo_path)) {
            $options['photo'] = $storage->get($model->card_photo_path);
            if (! is_string($options['photo'])) {
                throw new RuntimeException('Cannot read legacy artwork source.');
            }
        }
        if (CardDesign::style($model) !== 'simple' && $model->stamp_icon_path && $storage->exists($model->stamp_icon_path)) {
            $options['custom_icon'] = $storage->get($model->stamp_icon_path);
            if (! is_string($options['custom_icon'])) {
                throw new RuntimeException('Cannot read legacy stamp source.');
            }
        }

        return $this->styleHashes[$key] = substr(hash('sha256', serialize($options)), 0, 24);
    }

    private function sharedStyle(string $path, array $hashes): bool
    {
        return preg_match('#^wallet/card-styles-v1/([a-f0-9]{24})/#', $path, $m) && isset($hashes[$m[1]]);
    }

    private function deleteFile(string $disk, string $path): void
    {
        $storage = Storage::disk($disk);
        if ($storage->exists($path) && (! $storage->delete($path) || $storage->exists($path))) {
            throw new RuntimeException('File deletion was not confirmed.');
        }
    }

    private function validateAssetPath(string $path): void
    {
        $this->validateRelativePath($path);
        if (! preg_match('#^(logos|pass-logos|pass-heroes|card-designs|wallet/(programs|google/stamp-strips|card-styles-v[12]))/#', $path)) {
            throw new RuntimeException('File is outside merchant artwork storage.');
        }
    }

    private function validateRelativePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || preg_match('#(^|/)\.{1,2}(/|$)|[\x00-\x1f]#', $path)) {
            throw new RuntimeException('Unsafe cleanup path.');
        }
    }
}
