<?php

namespace App\Services\Wallet\Artwork;

use RuntimeException;

class WalletImageDecoder
{
    public function __construct(private readonly WalletImageValidator $validator) {}

    public function decode(string $contents): DecodedWalletImage
    {
        $inspection = $this->validator->inspectBinary($contents);
        if (! $inspection->isValid) {
            throw new RuntimeException(implode(' ', $inspection->errors));
        }

        $image = @imagecreatefromstring($contents);
        if (! $image) {
            throw new RuntimeException('The wallet image could not be decoded.');
        }

        if ($inspection->mimeType === 'image/jpeg') {
            $image = $this->applyJpegOrientation($image, $contents);
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return new DecodedWalletImage(
            $image,
            imagesx($image),
            imagesy($image),
            (string) $inspection->mimeType,
            $inspection->hasAlpha,
        );
    }

    private function applyJpegOrientation(\GdImage $image, string $contents): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'kawhe_exif_');
        if ($temporaryPath === false) {
            return $image;
        }

        try {
            file_put_contents($temporaryPath, $contents);
            $orientation = (int) (@exif_read_data($temporaryPath)['Orientation'] ?? 1);
            $angle = match ($orientation) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };

            if ($angle === 0) {
                return $image;
            }

            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            $rotated = imagerotate($image, $angle, $transparent);
            if (! $rotated) {
                return $image;
            }

            imagedestroy($image);
            imagesavealpha($rotated, true);

            return $rotated;
        } finally {
            @unlink($temporaryPath);
        }
    }
}
