<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Support\StoreAssets;
use Illuminate\Support\Facades\Log;

class GoogleWalletStampStripRenderer
{
    private const RENDER_VERSION = 'v7';

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
        $walletCardStyle = in_array($store->wallet_card_style, Store::WALLET_CARD_STYLES, true)
            ? $store->wallet_card_style
            : Store::WALLET_CARD_STYLE_CLASSIC;
        $walletPattern = in_array($store->wallet_background_pattern, Store::WALLET_BACKGROUND_PATTERNS, true)
            ? $store->wallet_background_pattern
            : Store::WALLET_BACKGROUND_PATTERN_ORGANIC;
        $walletPatternColor = $this->normalizeHexColor($store->wallet_pattern_color) ?? $accent;

        $stateHash = substr(sha1(implode('|', [
            self::RENDER_VERSION,
            $walletCardStyle,
            $walletPattern,
            $walletPatternColor,
            (string) ($store->wallet_stamp_icon_path ?? ''),
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

        $stampIconBinary = null;
        if ($walletCardStyle === Store::WALLET_CARD_STYLE_ABSTRACT && $this->isRasterIconPath($store->wallet_stamp_icon_path) && StoreAssets::exists($store->wallet_stamp_icon_path)) {
            $stampIconBinary = StoreAssets::get($store->wallet_stamp_icon_path);
        }

        $pngBinary = $this->renderPng(
            target: $target,
            stamps: $stamps,
            backgroundHex: $background,
            accentHex: $accent,
            foregroundHex: $foreground,
            heroBinary: $heroBinary,
            walletCardStyle: $walletCardStyle,
            walletPattern: $walletPattern,
            patternHex: $walletPatternColor,
            stampIconBinary: $stampIconBinary
        );
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
        ?string $heroBinary = null,
        string $walletCardStyle = Store::WALLET_CARD_STYLE_CLASSIC,
        string $walletPattern = Store::WALLET_BACKGROUND_PATTERN_ORGANIC,
        ?string $patternHex = null,
        ?string $stampIconBinary = null
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

        if ($walletCardStyle === Store::WALLET_CARD_STYLE_ABSTRACT) {
            $this->drawAbstractBackground($image, $width, $height, $backgroundHex, $patternHex ?? $accentHex, $foregroundHex, $walletPattern);
        }

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
        $overlayAlpha = $walletCardStyle === Store::WALLET_CARD_STYLE_ABSTRACT ? 72 : 82;
        $overlay = imagecolorallocatealpha($image, $bgR, $bgG, $bgB, $overlayAlpha);
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
                    if ($walletCardStyle === Store::WALLET_CARD_STYLE_ABSTRACT) {
                        $this->drawStampIcon($image, $x, $y, $circleDiameter, $bgColor, $index === $target, $stampIconBinary);
                    }
                } else {
                    $emptyFill = $walletCardStyle === Store::WALLET_CARD_STYLE_ABSTRACT
                        ? imagecolorallocatealpha($image, $bgR, $bgG, $bgB, 62)
                        : $bgColor;
                    imagefilledellipse($image, $x, $y, $circleDiameter, $circleDiameter, $emptyFill);
                    imageellipse($image, $x, $y, $circleDiameter, $circleDiameter, $fgColor);
                    if ($walletCardStyle === Store::WALLET_CARD_STYLE_ABSTRACT && $index === $target) {
                        $this->drawGiftIcon($image, $x, $y, $circleDiameter, $fgColor);
                    }
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

    protected function drawAbstractBackground($image, int $width, int $height, string $backgroundHex, string $accentHex, string $foregroundHex, string $walletPattern): void
    {
        [$bgR, $bgG, $bgB] = $this->hexToRgb($backgroundHex);
        [$acR, $acG, $acB] = $this->hexToRgb($accentHex);
        [$fgR, $fgG, $fgB] = $this->hexToRgb($foregroundHex);

        for ($y = 0; $y < $height; $y++) {
            $ratio = $height > 1 ? $y / ($height - 1) : 0;
            $r = (int) round(($bgR * (1 - $ratio)) + ($acR * $ratio * 0.55) + ($bgR * $ratio * 0.45));
            $g = (int) round(($bgG * (1 - $ratio)) + ($acG * $ratio * 0.55) + ($bgG * $ratio * 0.45));
            $b = (int) round(($bgB * (1 - $ratio)) + ($acB * $ratio * 0.55) + ($bgB * $ratio * 0.45));
            imageline($image, 0, $y, $width, $y, imagecolorallocate($image, $r, $g, $b));
        }

        $accentSoft = imagecolorallocatealpha($image, $acR, $acG, $acB, 54);
        $foregroundSoft = imagecolorallocatealpha($image, $fgR, $fgG, $fgB, 94);
        $line = imagecolorallocatealpha($image, $fgR, $fgG, $fgB, 76);
        $accentLine = imagecolorallocatealpha($image, $acR, $acG, $acB, 58);

        if ($walletPattern === Store::WALLET_BACKGROUND_PATTERN_DOTS) {
            for ($y = 22; $y < $height; $y += 28) {
                for ($x = 22; $x < $width; $x += 28) {
                    imagefilledellipse($image, $x, $y, 5, 5, $accentLine);
                }
            }
        } elseif ($walletPattern === Store::WALLET_BACKGROUND_PATTERN_GRID) {
            imagesetthickness($image, 2);
            for ($x = 0; $x < $width; $x += 42) {
                imageline($image, $x, 0, $x, $height, $accentLine);
            }
            for ($y = 0; $y < $height; $y += 42) {
                imageline($image, 0, $y, $width, $y, $accentLine);
            }
        } elseif ($walletPattern === Store::WALLET_BACKGROUND_PATTERN_DIAGONAL) {
            imagesetthickness($image, 3);
            for ($x = -$height; $x < $width; $x += 34) {
                imageline($image, $x, $height, $x + $height, 0, $accentLine);
            }
        } elseif ($walletPattern === Store::WALLET_BACKGROUND_PATTERN_WAVES) {
            imagesetthickness($image, 4);
            for ($y = -20; $y < $height + 60; $y += 42) {
                imagearc($image, (int) ($width * 0.25), $y, (int) ($width * 0.7), 90, 0, 180, $accentLine);
                imagearc($image, (int) ($width * 0.75), $y, (int) ($width * 0.7), 90, 180, 360, $accentLine);
            }
        } else {
            imagefilledellipse($image, (int) ($width * 0.12), (int) ($height * 0.2), 340, 180, $accentSoft);
            imagefilledellipse($image, (int) ($width * 0.9), (int) ($height * 0.72), 420, 220, $foregroundSoft);
            imagesetthickness($image, 4);
            imagearc($image, (int) ($width * 0.16), (int) ($height * 0.95), 420, 220, 205, 350, $line);
            imagearc($image, (int) ($width * 0.86), (int) ($height * 0.08), 360, 190, 20, 175, $line);
        }

        imagesetthickness($image, 1);
    }

    protected function drawStampIcon($image, int $centerX, int $centerY, int $circleDiameter, int $color, bool $isGift, ?string $stampIconBinary = null): void
    {
        if ($isGift) {
            $this->drawGiftIcon($image, $centerX, $centerY, $circleDiameter, $color);
            return;
        }

        if ($stampIconBinary && $this->drawUploadedStampIcon($image, $stampIconBinary, $centerX, $centerY, $circleDiameter)) {
            return;
        }

        $scale = max(0.4, $circleDiameter / 88);
        $cupW = (int) round(26 * $scale);
        $cupH = (int) round(17 * $scale);
        $cupX = $centerX - (int) floor($cupW / 2);
        $cupY = $centerY - (int) floor($cupH / 2) + (int) round(5 * $scale);

        imagesetthickness($image, max(2, (int) round(4 * $scale)));
        imagearc($image, $centerX, $cupY + (int) round(2 * $scale), $cupW, (int) round(13 * $scale), 0, 180, $color);
        imageline($image, $cupX, $cupY + (int) round(2 * $scale), $cupX + (int) round(4 * $scale), $cupY + $cupH, $color);
        imageline($image, $cupX + $cupW, $cupY + (int) round(2 * $scale), $cupX + $cupW - (int) round(4 * $scale), $cupY + $cupH, $color);
        imagearc($image, $cupX + $cupW + (int) round(6 * $scale), $cupY + (int) round(9 * $scale), (int) round(14 * $scale), (int) round(14 * $scale), 270, 90, $color);
        imageline($image, $cupX + (int) round(2 * $scale), $cupY + $cupH + (int) round(4 * $scale), $cupX + $cupW - (int) round(2 * $scale), $cupY + $cupH + (int) round(4 * $scale), $color);
        imagesetthickness($image, max(1, (int) round(2 * $scale)));
        imagearc($image, $centerX - (int) round(6 * $scale), $cupY - (int) round(8 * $scale), (int) round(10 * $scale), (int) round(15 * $scale), 260, 75, $color);
        imagearc($image, $centerX + (int) round(6 * $scale), $cupY - (int) round(8 * $scale), (int) round(10 * $scale), (int) round(15 * $scale), 260, 75, $color);
        imagesetthickness($image, 1);
    }

    protected function drawUploadedStampIcon($image, string $stampIconBinary, int $centerX, int $centerY, int $circleDiameter): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $source = @imagecreatefromstring($stampIconBinary);
        if (! $source) {
            return false;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($source);
            return false;
        }

        $maxSize = (int) floor($circleDiameter * 0.56);
        $scale = min($maxSize / $srcW, $maxSize / $srcH);
        $drawW = max(1, (int) floor($srcW * $scale));
        $drawH = max(1, (int) floor($srcH * $scale));
        $dstX = $centerX - (int) floor($drawW / 2);
        $dstY = $centerY - (int) floor($drawH / 2);

        imagecopyresampled($image, $source, $dstX, $dstY, 0, 0, $drawW, $drawH, $srcW, $srcH);
        imagedestroy($source);

        return true;
    }

    protected function drawGiftIcon($image, int $centerX, int $centerY, int $circleDiameter, int $color): void
    {
        $scale = max(0.4, $circleDiameter / 88);
        $boxW = (int) round(30 * $scale);
        $boxH = (int) round(24 * $scale);
        $x = $centerX - (int) floor($boxW / 2);
        $y = $centerY - (int) floor($boxH / 2) + (int) round(4 * $scale);
        $lidH = (int) round(8 * $scale);

        imagesetthickness($image, max(2, (int) round(4 * $scale)));
        imagerectangle($image, $x, $y, $x + $boxW, $y + $lidH, $color);
        imagerectangle($image, $x + (int) round(3 * $scale), $y + $lidH, $x + $boxW - (int) round(3 * $scale), $y + $boxH, $color);
        imageline($image, $centerX, $y, $centerX, $y + $boxH, $color);
        imagearc($image, $centerX - (int) round(7 * $scale), $y - (int) round(2 * $scale), (int) round(15 * $scale), (int) round(12 * $scale), 195, 25, $color);
        imagearc($image, $centerX + (int) round(7 * $scale), $y - (int) round(2 * $scale), (int) round(15 * $scale), (int) round(12 * $scale), 155, 345, $color);
        imagesetthickness($image, 1);
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

    protected function isRasterIconPath(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp'], true);
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
