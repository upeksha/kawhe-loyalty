#!/usr/bin/env php
<?php

// Read-only source snapshot. Never uploads, deletes, or restores source objects.
declare(strict_types=1);

umask(0077);
require (getenv('APP_DIR') ?: dirname(__DIR__)).'/vendor/autoload.php';
$app = require (getenv('APP_DIR') ?: dirname(__DIR__)).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $destination = $argv[1] ?? '';
    if ($destination === '' || file_exists($destination)) {
        throw new RuntimeException('Supply a new, private destination directory.');
    }
    $diskName = config('filesystems.assets_disk', 'public');
    if (in_array('--config-stdin', $argv, true)) {
        // Allows a local drill using a remote disk configuration piped directly
        // into memory. Never persist credentials in the snapshot or manifest.
        $diskConfig = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        if (($diskConfig['driver'] ?? '') !== 's3') {
            throw new RuntimeException('Streamed configuration must describe S3 storage.');
        }
        $diskName = 'release_snapshot';
        config(['filesystems.disks.'.$diskName => $diskConfig]);
    }
    $disk = Illuminate\Support\Facades\Storage::disk($diskName);
    mkdir($destination, 0700, true);
    $entries = [];
    // Backup objects can share the asset bucket; do not recursively back up backups.
    foreach ($disk->getDriver()->listContents('', true) as $item) {
        if (! $item->isFile()) {
            continue;
        }
        $key = $item->path();
        if (str_starts_with($key, 'backups/')) {
            continue;
        }
        if ($key === '' || str_starts_with($key, '/') || str_contains($key, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $key)) {
            throw new RuntimeException('Unsafe object path in source storage.');
        }
        $target = $destination.'/files/'.$key;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        $input = $disk->readStream($key);
        if (! is_resource($input)) {
            throw new RuntimeException('Cannot read source object.');
        }
        $output = fopen($target, 'xb');
        $bytes = stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);
        if ($bytes === false || $bytes !== $item->fileSize()) {
            throw new RuntimeException('Source object size changed or copy failed.');
        }
        $entries[] = ['key' => $key, 'bytes' => $bytes, 'sha256' => hash_file('sha256', $target)];
    }
    if (! $entries) {
        throw new RuntimeException('Empty snapshot refused; inspect source configuration.');
    }
    file_put_contents($destination.'/manifest.json', json_encode([
        'version' => 1, 'excluded_prefixes' => ['backups/'], 'files' => $entries,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo count($entries)." artwork objects copied and hashed.\n";
} catch (Throwable $e) {
    // Provider exception messages may contain credentials or signed URLs.
    fwrite(STDERR, "Artwork snapshot failed; partial directory must not be used as a backup.\n");
    exit(1);
}
