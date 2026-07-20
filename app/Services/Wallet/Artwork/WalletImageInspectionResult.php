<?php

namespace App\Services\Wallet\Artwork;

class WalletImageInspectionResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $mimeType = null,
        public readonly bool $hasAlpha = false,
        public readonly ?float $aspectRatio = null,
        public readonly ?int $pixelCount = null,
    ) {}

    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mimeType,
            'has_alpha' => $this->hasAlpha,
            'aspect_ratio' => $this->aspectRatio,
            'pixel_count' => $this->pixelCount,
        ];
    }
}
