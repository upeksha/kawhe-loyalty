<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreAssets
{
    public static function diskName(): string
    {
        return (string) config('filesystems.assets_disk', 'public');
    }

    public static function disk()
    {
        return Storage::disk(static::diskName());
    }

    public static function exists(?string $path): bool
    {
        return ! empty($path) && static::disk()->exists($path);
    }

    public static function delete(?string $path): void
    {
        if (! empty($path) && static::disk()->exists($path)) {
            static::disk()->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return static::disk()->url($path);
    }

    public static function storeUploaded(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, static::diskName());
    }

    public static function get(?string $path): ?string
    {
        if (! static::exists($path)) {
            return null;
        }

        return static::disk()->get($path);
    }

    public static function put(string $path, string $contents): void
    {
        static::disk()->put($path, $contents);
    }

    public static function putImmutablePng(string $path, string $contents): void
    {
        static::disk()->put($path, $contents, [
            'visibility' => 'public',
            'ContentType' => 'image/png',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function files(string $directory): array
    {
        return static::disk()->allFiles($directory);
    }

    public static function lastModified(string $path): ?int
    {
        try {
            return static::disk()->lastModified($path);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function deleteMatching(string $directory, callable $predicate): int
    {
        $paths = collect(static::disk()->allFiles($directory))
            ->filter($predicate)
            ->values();

        if ($paths->isEmpty()) {
            return 0;
        }

        static::disk()->delete($paths->all());

        return $paths->count();
    }

    public static function deleteGeneratedStampStripsForStore(int $storeId): int
    {
        $prefix = sprintf('store_%d_', $storeId);

        return static::deleteMatching('wallet/google/stamp-strips', function (string $path) use ($prefix) {
            return str_contains(basename($path), $prefix);
        });
    }

    public static function deleteGeneratedStampStripsForAccount(int $accountId): int
    {
        $needle = sprintf('_account_%d_', $accountId);

        return static::deleteMatching('wallet/google/stamp-strips', function (string $path) use ($needle) {
            return str_contains(basename($path), $needle);
        });
    }

    public static function localTempPath(?string $path, string $extension = 'bin'): ?string
    {
        $contents = static::get($path);
        if ($contents === null) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'kawhe_asset_');
        if ($tmp === false) {
            return null;
        }

        $target = $tmp.'.'.ltrim($extension, '.');
        rename($tmp, $target);
        file_put_contents($target, $contents);

        return $target;
    }
}
