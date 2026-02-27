<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleWalletStampStripRenderer
{
    /**
     * Generate a stamp-strip PNG in public storage and return its relative path.
     */
    public function generateForAccount(LoyaltyAccount $account): ?string
    {
        $account->loadMissing('store');
        $store = $account->store;
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

        $target = max(1, (int) ($store->reward_target ?? 10));
        $stamps = max(0, min((int) $account->stamp_count, $target));

        $background = $this->normalizeHexColor($store->background_color)
            ?? $this->normalizeHexColor($store->brand_color)
            ?? '#1F2937';

        $accent = $this->normalizeHexColor($store->brand_color) ?? '#FFFFFF';
        $foreground = $this->bestContrastTextColor($background);

        $stateHash = substr(sha1(implode('|', [
            $target,
            $stamps,
            $background,
            $accent,
            $foreground,
            (string) ($store->updated_at?->timestamp ?? 0),
        ])), 0, 16);

        $relativePath = sprintf('wallet/google/stamp-strips/store_%d_account_%d_%s.png', $store->id, $account->id, $stateHash);
        if (Storage::disk('public')->exists($relativePath)) {
            return $relativePath;
        }

        $pngBinary = $this->renderPng($target, $stamps, $background, $accent, $foreground);
        if (! $pngBinary) {
            return null;
        }

        Storage::disk('public')->put($relativePath, $pngBinary);
        return $relativePath;
    }

    protected function renderPng(int $target, int $stamps, string $backgroundHex, string $accentHex, string $foregroundHex): ?string
    {
        $width = 1032;
        $height = 336;
        $paddingX = 52;
        $paddingTop = 34;

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
        $accentColor = imagecolorallocate($image, $acR, $acG, $acB);
        $softAccent = imagecolorallocate($image, (int) (($acR + $bgR) / 2), (int) (($acG + $bgG) / 2), (int) (($acB + $bgB) / 2));

        imagefill($image, 0, 0, $bgColor);

        // Subtle top strip for depth.
        imagefilledrectangle($image, 0, 0, $width, 62, $softAccent);

        $columns = min(max($target, 1), 6);
        $rows = (int) ceil($target / $columns);
        $maxRows = 2;
        if ($rows > $maxRows) {
            $rows = $maxRows;
            $columns = (int) ceil($target / $rows);
        }

        $usableWidth = $width - ($paddingX * 2);
        $gap = 18;
        $circleDiameter = (int) floor(($usableWidth - (($columns - 1) * $gap)) / $columns);
        $circleDiameter = max(34, min($circleDiameter, 88));
        $circleRadius = (int) floor($circleDiameter / 2);

        $startY = $paddingTop + $circleRadius;
        $index = 0;
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
                    imagesetthickness($image, 4);
                    imageellipse($image, $x, $y, $circleDiameter, $circleDiameter, $fgColor);
                    imagesetthickness($image, 1);
                }
            }
        }

        $progressText = sprintf('%d/%d', $stamps, $target);
        $bottomBarTop = $height - 72;
        imagefilledrectangle($image, 0, $bottomBarTop, $width, $height, $softAccent);

        imagestring($image, 5, 32, $bottomBarTop + 22, 'PROGRAM', $fgColor);
        imagestring($image, 5, 125, $bottomBarTop + 22, 'REWARDS', $fgColor);
        imagestring($image, 5, $width - 150, $bottomBarTop + 22, $progressText, $accentColor);

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
