#!/usr/bin/env php
<?php

declare(strict_types=1);

use Aws\S3\S3Client;

$appDir = getenv('APP_DIR') ?: '/var/www/kawhe';
$autoload = $appDir . '/vendor/autoload.php';

if (! file_exists($autoload)) {
    fwrite(STDERR, "Missing Composer autoload at {$autoload}\n");
    exit(1);
}

require $autoload;

if ($argc < 3) {
    fwrite(STDERR, "Usage: upload-to-spaces.php <local-file> <remote-key>\n");
    exit(1);
}

$localFile = $argv[1];
$remoteKey = ltrim($argv[2], '/');

if (! is_file($localFile)) {
    fwrite(STDERR, "Local file not found: {$localFile}\n");
    exit(1);
}

$key = getenv('OPS_BACKUP_SPACES_ACCESS_KEY_ID') ?: getenv('ASSETS_ACCESS_KEY_ID') ?: getenv('AWS_ACCESS_KEY_ID');
$secret = getenv('OPS_BACKUP_SPACES_SECRET_ACCESS_KEY') ?: getenv('ASSETS_SECRET_ACCESS_KEY') ?: getenv('AWS_SECRET_ACCESS_KEY');
$region = getenv('OPS_BACKUP_SPACES_REGION') ?: getenv('ASSETS_DEFAULT_REGION') ?: getenv('AWS_DEFAULT_REGION') ?: 'syd1';
$bucket = getenv('OPS_BACKUP_SPACES_BUCKET') ?: getenv('ASSETS_BUCKET') ?: getenv('AWS_BUCKET');
$endpoint = getenv('OPS_BACKUP_SPACES_ENDPOINT') ?: getenv('ASSETS_ENDPOINT') ?: getenv('AWS_ENDPOINT');

if (! $key || ! $secret || ! $bucket || ! $endpoint) {
    fwrite(STDERR, "Missing Spaces configuration for backup upload.\n");
    exit(1);
}

$client = new S3Client([
    'version' => 'latest',
    'region' => $region,
    'endpoint' => $endpoint,
    'credentials' => [
        'key' => $key,
        'secret' => $secret,
    ],
]);

$client->putObject([
    'Bucket' => $bucket,
    'Key' => $remoteKey,
    'SourceFile' => $localFile,
    'ACL' => 'private',
]);

fwrite(STDOUT, $remoteKey . PHP_EOL);
