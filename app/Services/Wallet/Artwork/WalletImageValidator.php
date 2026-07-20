<?php

namespace App\Services\Wallet\Artwork;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class WalletImageValidator
{
    public const MAX_PIXEL_COUNT = 40_000_000;

    private const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    /**
     * @param  array<string, string>  $fields
     * @return array<int, string>
     */
    public function warningsForRequest(Request $request, array $fields): array
    {
        $warnings = [];
        foreach ($fields as $field => $purpose) {
            $file = $request->file($field);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $result = $this->inspectUploadedFile($file, $purpose);
            foreach ($result->warnings as $warning) {
                $warnings[] = $warning;
            }
        }

        return array_values(array_unique($warnings));
    }

    public function inspectUploadedFile(UploadedFile $file, string $purpose = 'image'): WalletImageInspectionResult
    {
        $contents = @file_get_contents($file->getRealPath());

        if ($contents === false || $contents === '') {
            return new WalletImageInspectionResult(false, ['The uploaded image could not be read.']);
        }

        return $this->inspectBinary($contents, $purpose, $file->getMimeType());
    }

    public function inspectBinary(string $contents, string $purpose = 'image', ?string $declaredMime = null): WalletImageInspectionResult
    {
        $errors = [];
        $warnings = [];
        $info = @getimagesizefromstring($contents);
        $detectedMime = $info['mime'] ?? null;

        if (! $info || ! $detectedMime || ! in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            return new WalletImageInspectionResult(false, ['Use a valid PNG, JPG, JPEG, or WebP image.']);
        }

        if ($declaredMime && (! in_array($declaredMime, self::ALLOWED_MIME_TYPES, true) || $declaredMime !== $detectedMime)) {
            $errors[] = 'The uploaded file content does not match a supported image format.';
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $pixelCount = $width * $height;
        $aspectRatio = $height > 0 ? $width / $height : null;

        if ($width <= 0 || $height <= 0) {
            $errors[] = 'The uploaded image has invalid dimensions.';
        }

        if ($pixelCount > self::MAX_PIXEL_COUNT) {
            $errors[] = 'The uploaded image dimensions are too large to process safely.';
        }

        if ($detectedMime === 'image/webp' && str_contains(substr($contents, 0, 256), 'ANIM')) {
            $errors[] = 'Animated WebP images are not supported. Upload a still image.';
        }

        $image = empty($errors) ? @imagecreatefromstring($contents) : false;
        if (! $image && empty($errors)) {
            $errors[] = 'The uploaded image is corrupted or uses an unsupported colour format.';
        }

        $hasAlpha = false;
        $hasTransparentEdge = false;
        if ($image) {
            $hasAlpha = $detectedMime === 'image/png' || $detectedMime === 'image/webp';
            $hasTransparentEdge = $hasAlpha && $this->hasTransparentEdge($image, $width, $height);
            imagedestroy($image);
        }

        if (empty($errors)) {
            if ($purpose === 'logo') {
                if ($width < 320 || $height < 100) {
                    $warnings[] = 'The wallet logo is low resolution and may look soft on high-density screens.';
                }
                if ($aspectRatio !== null && ($aspectRatio > 8 || $aspectRatio < 0.35)) {
                    $warnings[] = 'The wallet logo has an extreme aspect ratio and may appear very small.';
                }
                if ($aspectRatio !== null && $aspectRatio > 4.5) {
                    $warnings[] = 'This wide logo may be difficult to read after Google applies its circular mask.';
                }
                if (! $hasTransparentEdge) {
                    $warnings[] = 'Add transparent spacing around the wallet logo to reduce clipping risk.';
                }
            }

            if ($purpose === 'hero') {
                if ($width < 750 || $height < 288) {
                    $warnings[] = 'The wallet hero image is low resolution and may look soft on some devices.';
                }
                if ($aspectRatio !== null && ($aspectRatio > 4.5 || $aspectRatio < 0.8)) {
                    $warnings[] = 'The wallet hero image will be centre-cropped significantly on Apple or Google Wallet.';
                }
            }

            if ($pixelCount > 20_000_000) {
                $warnings[] = 'This image is very large and will be reduced for wallet use.';
            }
        }

        return new WalletImageInspectionResult(
            empty($errors),
            $errors,
            $warnings,
            $width,
            $height,
            $detectedMime,
            $hasAlpha,
            $aspectRatio,
            $pixelCount,
        );
    }

    private function hasTransparentEdge(\GdImage $image, int $width, int $height): bool
    {
        $points = [
            [0, 0],
            [max(0, $width - 1), 0],
            [0, max(0, $height - 1)],
            [max(0, $width - 1), max(0, $height - 1)],
            [intdiv(max(0, $width - 1), 2), 0],
            [intdiv(max(0, $width - 1), 2), max(0, $height - 1)],
        ];

        foreach ($points as [$x, $y]) {
            $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            if (($color['alpha'] ?? 0) >= 100) {
                return true;
            }
        }

        return false;
    }
}
