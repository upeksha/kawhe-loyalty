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

        $target = $tmp . '.' . ltrim($extension, '.');
        rename($tmp, $target);
        file_put_contents($target, $contents);

        return $target;
    }
}
