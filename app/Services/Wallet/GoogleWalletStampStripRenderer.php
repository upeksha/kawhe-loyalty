<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use App\Support\StoreAssets;
use Illuminate\Support\Facades\Log;

class GoogleWalletStampStripRenderer
{
    private const RENDER_VERSION = 'v5';

    /**
     * Generate a stamp-strip PNG on the configured asset disk and return its relative path.
     */
    public function generateForAccount(LoyaltyAccount $account): ?string
    {
        $account->loadMissing(['store', 'loyaltyProgram']);
        $store = $account->store;
        $program = $account->resolvedProgram() ?? $store;
        if (! $store) {
            return null;
        }

        if (! function_exists('imagecreatetruecolor')) {
            Log::warning('Google Wallet stamp strip skipped: GD extension not available', [
                'account_id' => $account->id,
                'store_id' => $account->store_id,
            ]);
            return null;
        }

        $target = max(1, (int) ($program->reward_target ?? 10));
        $stamps = max(0, min((int) $account->stamp_count, $target));

        $background = $this->normalizeHexColor($program->background_color)
            ?? $this->normalizeHexColor($program->brand_color)
            ?? '#1F2937';

        $accent = $this->normalizeHexColor($program->brand_color) ?? '#FFFFFF';
        $foreground = $this->bestContrastTextColor($background);

        $stateHash = substr(sha1(implode('|', [
            self::RENDER_VERSION,
            $target,
            $stamps,
            $background,
            $accent,
            $foreground,
            (string) ($program->updated_at?->timestamp ?? $store->updated_at?->timestamp ?? 0),
        ])), 0, 16);

        $relativePath = sprintf('wallet/google/stamp-strips/program_%d_account_%d_%s.png', $program->id ?? 0, $account->id, $stateHash);
        if (StoreAssets::exists($relativePath)) {
            return $relativePath;
        }

        $heroBinary = null;
        if (! empty($program->pass_hero_image_path) && StoreAssets::exists($program->pass_hero_image_path)) {
            $heroBinary = StoreAssets::get($program->pass_hero_image_path);
        }

        $pngBinary = $this->renderPng($target, $stamps, $background, $accent, $foreground, $heroBinary);
        if (! $pngBinary) {
            return null;
        }

        StoreAssets::put($relativePath, $pngBinary);
        return $relativePath;
    }

    protected function renderPng(
        int $target,
        int $stamps,
        string $backgroundHex,
        string $accentHex,
        string $foregroundHex,
        ?string $heroBinary = null
    ): ?string
    {
        $width = 1032;
        $height = 230;
        $paddingX = 52;
        $paddingTop = 28;

        $image = imagecreatetruecolor($width, $height);
        if (! $image) {
            return null;
        }

        imageantialias($image, true);

        [$bgR, $bgG, $bgB] = $this->hexToRgb($backgroundHex);
        [$fgR, $fgG, $fgB] = $this->hexToRgb($foregroundHex);
        [$acR, $acG, $acB] = $this->hexToRgb($accentHex);

        $bgColor = imagecolorallocate($image, $bgR, $bgG, $bgB);
        $fgColor = imagecolorallocate($image, $fgR, $fgG, $fgB);
        imagefill($image, 0, 0, $bgColor);

        // Blend store hero image under circles for front-card visual parity.
        if ($heroBinary) {
            $heroSource = @imagecreatefromstring($heroBinary);
            if ($heroSource !== false) {
                $srcW = imagesx($heroSource);
                $srcH = imagesy($heroSource);
                if ($srcW > 0 && $srcH > 0) {
                    $scale = max($width / $srcW, $height / $srcH);
                    $drawW = (int) ceil($srcW * $scale);
                    $drawH = (int) ceil($srcH * $scale);
                    $dstX = (int) floor(($width - $drawW) / 2);
                    $dstY = (int) floor(($height - $drawH) / 2);
                    imagecopyresampled($image, $heroSource, $dstX, $dstY, 0, 0, $drawW, $drawH, $srcW, $srcH);
                }
                imagedestroy($heroSource);
            }
        }

        // Slight dark overlay for consistent circle contrast.
        $overlay = imagecolorallocatealpha($image, $bgR, $bgG, $bgB, 82);
        imagefilledrectangle($image, 0, 0, $width, $height, $overlay);

        $columns = min(max($target, 1), 10);
        $rows = (int) ceil($target / $columns);
        if ($rows > 2) {
            $rows = 2;
            $columns = (int) ceil($target / 2);
        }

        $usableWidth = $width - ($paddingX * 2);
        $gap = 18;
        $circleDiameter = (int) floor(($usableWidth - (($columns - 1) * $gap)) / $columns);
        $circleDiameter = max(34, min($circleDiameter, 88));
        $circleRadius = (int) floor($circleDiameter / 2);

        $totalCirclesHeight = ($rows * $circleDiameter) + (($rows - 1) * 16);
        $startY = (int) floor((($height - $totalCirclesHeight) / 2)) + $circleRadius;
        $startY = max($paddingTop + $circleRadius, $startY);
        $index = 0;
        imagesetthickness($image, 4);
        for ($row = 0; $row < $rows; $row++) {
            $itemsInRow = min($columns, $target - ($row * $columns));
            $rowWidth = ($itemsInRow * $circleDiameter) + (($itemsInRow - 1) * $gap);
            $startX = (int) floor(($width - $rowWidth) / 2) + $circleRadius;
            $y = $startY + ($row * ($circleDiameter + 16));

            for ($col = 0; $col < $itemsInRow; $col++) {
                $index++;
                $x = $startX + ($col * ($circleDiameter + $gap));
                if ($index <= $stamps) {
                    imagefilledellipse($image, $x, $y, $circleDiameter, $circleDiameter, $fgColor);
                    imageellipse($image, $x, $y, $circleDiameter, $circleDiameter, $fgColor);
                } else {
                    imagefilledellipse($image, $x, $y, $circleDiameter, $circleDiameter, $bgColor);
                    imageellipse($image, $x, $y, $circleDiameter, $circleDiameter, $fgColor);
                }
            }
        }
        imagesetthickness($image, 1);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png !== false ? $png : null;
    }

    protected function normalizeHexColor(?string $hex): ?string
    {
        if (! $hex) {
            return null;
        }

        $hex = trim($hex);
        if (! str_starts_with($hex, '#')) {
            $hex = '#' . $hex;
        }

        if (! preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $hex)) {
            return null;
        }

        if (strlen($hex) === 4) {
            return sprintf(
                '#%s%s%s%s%s%s',
                $hex[1],
                $hex[1],
                $hex[2],
                $hex[2],
                $hex[3],
                $hex[3]
            );
        }

        return strtoupper($hex);
    }

    protected function bestContrastTextColor(string $backgroundHex): string
    {
        [$r, $g, $b] = $this->hexToRgb($backgroundHex);
        $luminance = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
        return $luminance > 146 ? '#111111' : '#FFFFFF';
    }

    /**
     * @return array{int, int, int}
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
