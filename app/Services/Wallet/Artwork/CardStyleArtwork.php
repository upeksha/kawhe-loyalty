<?php

namespace App\Services\Wallet\Artwork;

/** Shared artwork for web and both Wallets. No customer identifiers are embedded. */
class CardStyleArtwork
{
    public const RENDERER_VERSION = 'card-style-v4.1';

    public function render(int $target, int $count, bool $ready, string $platform, array $options): string
    {
        [$w, $h] = match ($platform) {
            'apple' => [1125, 432], 'google' => [1032, 812], 'web' => [720, 470],
            default => throw new \InvalidArgumentException('Unsupported card platform'),
        };
        $target = max(1, $target);
        $count = max(0, min($target, $count));
        $im = imagecreatetruecolor($w, $h);
        imageantialias($im, true);
        $bg = $this->color($im, $options['background']);
        $hasCustomStampColor = isset($options['stamp_color']);
        $ink = $this->color($im, $options['stamp_color'] ?? $options['ink']);
        $photoOverlay = $platform === 'web' && $options['style'] === 'photo' && ! empty($options['transparent_photo']);
        if ($photoOverlay) {
            // The browser card already has the photo behind this reward/stamp overlay.
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
            imagealphablending($im, true);
            $bg = $this->color($im, '#182820');
            $ink = $this->color($im, $options['stamp_color'] ?? '#ffffff');
        } else {
            imagefill($im, 0, 0, $bg);
        }
        if ($options['style'] === 'photo' && $options['photo'] && ! $photoOverlay) {
            $photo = imagecreatefromstring($options['photo']);
            if ($photo) {
                // Photo artwork stays readable independently of the surrounding card colour.
                $bg = $this->color($im, '#182820');
                $ink = $this->color($im, $options['stamp_color'] ?? '#ffffff');
                $this->cover($im, $photo, $w, $h);
                imagedestroy($photo);
                if ($platform === 'web') {
                    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocatealpha($im, 0, 0, 0, $hasCustomStampColor ? 43 : 44));
                } else {
                    $this->shadePhotoForStamps($im, $w, $h, $hasCustomStampColor ? 43 : 48);
                }
            }
        }
        if ($options['style'] === 'illustrated') {
            $patterns = ['mountains' => 'organic', 'waves' => 'floral', 'botanical' => 'waves'];
            $name = $patterns[$options['pattern']] ?? 'organic';
            $pattern = imagecreatefrompng(public_path('images/card-patterns/'.$name.'-mask.png'));
            imagealphablending($pattern, false);
            [$red, $green, $blue] = $this->rgb($options['ink']);
            imagefilter($pattern, IMG_FILTER_COLORIZE, $red - 255, $green - 255, $blue - 255, 100);
            $this->cover($im, $pattern, $w, $h);
            imagedestroy($pattern);
        }
        $shape = $options['shape'] ?? 'circle';
        $rewardIcon = $options['reward_icon'] ?? 'match';
        $custom = $options['icon'] === 'custom' && $options['custom_icon'] ? imagecreatefromstring($options['custom_icon']) : null;
        if ($ready && $count === 0) {
            $size = min($h * .63, $w * .3);
            $rewardCustom = $rewardIcon === 'custom' && ! empty($options['custom_reward_icon'])
                ? imagecreatefromstring($options['custom_reward_icon']) : null;
            $this->stamp($im, $w / 2, $h / 2, $size, true, $bg, $ink, $rewardIcon === 'match' ? $options['icon'] : $rewardIcon, $rewardIcon === 'custom' ? $rewardCustom : ($rewardIcon === 'match' ? $custom : null), customStampColor: $hasCustomStampColor, shape: $shape);
            if ($rewardCustom) {
                imagedestroy($rewardCustom);
            }
            for ($i = 0; $i < 8; $i++) {
                $a = $i * M_PI / 4;
                imageline($im, (int) ($w / 2 + cos($a) * $size * .68), (int) ($h / 2 + sin($a) * $size * .68), (int) ($w / 2 + cos($a) * $size * .8), (int) ($h / 2 + sin($a) * $size * .8), $ink);
            }
        } elseif ($target > 20) {
            $this->stamp($im, $w / 2, $h * .34, min($h * .4, $w * .24), true, $bg, $ink, $options['icon'], $custom, customStampColor: $hasCustomStampColor, shape: $shape);
            $x = (int) ($w * .12);
            $y = (int) ($h * .73);
            $bw = (int) ($w * .76);
            $bh = (int) ($h * .06);
            imagerectangle($im, $x, $y, $x + $bw, $y + $bh, $ink);
            if ($count) {
                imagefilledrectangle($im, $x + 2, $y + 2, $x + 2 + (int) (($bw - 4) * $count / $target), $y + $bh - 2, $ink);
            }
        } else {
            $rows = $target <= 5 ? 1 : 2;
            $cols = (int) ceil($target / $rows);
            $heightLimit = $platform === 'google' ? .18 : .29;
            $size = min($w * .16, $h * $heightLimit, $w * .86 / ($cols * 1.2));
            $gap = $size * .25;
            $top = ($h - ($rows * $size + ($rows - 1) * $gap)) / 2;
            for ($i = 0; $i < $target; $i++) {
                $row = intdiv($i, $cols);
                $col = $i % $cols;
                $items = min($cols, $target - $row * $cols);
                $left = ($w - ($items * $size + ($items - 1) * $gap)) / 2;
                $this->stamp($im, $left + $col * ($size + $gap) + $size / 2, $top + $row * ($size + $gap) + $size / 2, $size, $i < $count, $bg, $ink, $options['icon'], $custom, walletOutline: $platform !== 'web', customStampColor: $hasCustomStampColor, shape: $shape);
            }
        }
        if ($custom) {
            imagedestroy($custom);
        }
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    private function stamp($im, float $x, float $y, float $size, bool $filled, int $bg, int $ink, string $icon, $custom, bool $walletOutline = false, bool $customStampColor = false, string $shape = 'circle'): void
    {
        imagesetthickness($im, max(2, (int) ($size * .02)));
        if (! $filled) {
            if ($shape !== 'circle') {
                $width = $walletOutline ? max(4, (int) round($size * .043)) : max(2, (int) ($size * .02));
                imagesetthickness($im, 1);
                for ($stroke = 0; $stroke < $width; $stroke++) {
                    $this->outlineShape($im, $x, $y, $size + $width - 1 - 2 * $stroke, $ink, $shape);
                }
            } elseif ($walletOutline) {
                // Concentric outlines leave the photo or artwork visible through the ring.
                imagesetthickness($im, 1);
                $width = max(4, (int) round($size * .043));
                $diameter = (int) $size + $width - 1;
                for ($stroke = 0; $stroke < $width; $stroke++, $diameter -= 2) {
                    imageellipse($im, (int) $x, (int) $y, $diameter, $diameter, $ink);
                }
            } else {
                imageellipse($im, (int) $x, (int) $y, (int) $size, (int) $size, $ink);
            }

            return;
        }
        $stampSurface = $custom && ! $customStampColor ? imagecolorallocate($im, 255, 255, 255) : $ink;
        $this->filledShape($im, $x, $y, $size, $stampSurface, $shape);
        $s = $size * .54;
        if ($custom) {
            if ($customStampColor) {
                $inset = (int) ($size * .8);
                $this->filledShape($im, $x, $y, $inset, imagecolorallocate($im, 255, 255, 255), $shape);
            }
            $ratio = min($s / imagesx($custom), $s / imagesy($custom));
            $cw = (int) (imagesx($custom) * $ratio);
            $ch = (int) (imagesy($custom) * $ratio);
            imagecopyresampled($im, $custom, (int) ($x - $cw / 2), (int) ($y - $ch / 2), 0, 0, $cw, $ch, imagesx($custom), imagesy($custom));
        } elseif ($icon === 'star') {
            $pts = [];
            for ($i = 0; $i < 10; $i++) {
                $a = $i * M_PI / 5 - M_PI / 2;
                $r = $s * ($i % 2 ? .23 : .5);
                $pts[] = (int) ($x + cos($a) * $r);
                $pts[] = (int) ($y + sin($a) * $r);
            }
            imagefilledpolygon($im, $pts, $bg);
        } elseif ($icon === 'heart') {
            imagefilledellipse($im, (int) ($x - $s * .22), (int) ($y - $s * .16), (int) ($s * .56), (int) ($s * .56), $bg);
            imagefilledellipse($im, (int) ($x + $s * .22), (int) ($y - $s * .16), (int) ($s * .56), (int) ($s * .56), $bg);
            imagefilledpolygon($im, [(int) ($x - $s * .48), (int) ($y - $s * .06), (int) ($x + $s * .48), (int) ($y - $s * .06), (int) $x, (int) ($y + $s * .48)], $bg);
        } elseif ($icon === 'gift') {
            $left = (int) ($x - $s * .4);
            $right = (int) ($x + $s * .4);
            imagefilledrectangle($im, $left, (int) ($y - $s * .12), $right, (int) ($y + $s * .4), $bg);
            imagefilledrectangle($im, (int) ($x - $s * .46), (int) ($y - $s * .22), (int) ($x + $s * .46), (int) ($y - $s * .05), $bg);
            imagefilledellipse($im, (int) ($x - $s * .16), (int) ($y - $s * .34), (int) ($s * .35), (int) ($s * .23), $bg);
            imagefilledellipse($im, (int) ($x + $s * .16), (int) ($y - $s * .34), (int) ($s * .35), (int) ($s * .23), $bg);
            imagefilledrectangle($im, (int) ($x - $s * .045), (int) ($y - $s * .22), (int) ($x + $s * .045), (int) ($y + $s * .4), $ink);
        } elseif ($icon === 'bean') {
            imagefilledellipse($im, (int) $x, (int) $y, (int) ($s * .7), (int) $s, $bg);
            imagesetthickness($im, max(2, (int) ($s * .08)));
            imageline($im, (int) ($x + $s * .1), (int) ($y - $s * .43), (int) ($x - $s * .1), (int) ($y + $s * .43), $ink);
        } else {
            $left = $x - $s / 2;
            $top = $y - $s / 2;
            imagearc($im, (int) ($left + $s * .79), (int) ($top + $s * .54), (int) ($s * .28), (int) ($s * .30), 270, 90, $bg);
            imagefilledrectangle($im, (int) ($left + $s * .16), (int) ($top + $s * .36), (int) ($left + $s * .70), (int) ($top + $s * .60), $bg);
            imagefilledellipse($im, (int) ($left + $s * .43), (int) ($top + $s * .59), (int) ($s * .54), (int) ($s * .40), $bg);
            imageline($im, (int) ($left + $s * .12), (int) ($top + $s * .89), (int) ($left + $s * .76), (int) ($top + $s * .89), $bg);
            foreach ([.34, .51] as $v) {
                imageline($im, (int) ($left + $s * $v), (int) ($top + $s * .08), (int) ($left + $s * ($v - .04)), (int) ($top + $s * .27), $bg);
            }
        }
    }

    private function filledShape($im, float $x, float $y, float $size, int $color, string $shape): void
    {
        if ($shape === 'circle') {
            imagefilledellipse($im, (int) $x, (int) $y, (int) $size, (int) $size, $color);

            return;
        }
        $left = (int) round($x - $size / 2);
        $top = (int) round($y - $size / 2);
        $right = (int) round($x + $size / 2);
        $bottom = (int) round($y + $size / 2);
        $radius = $shape === 'rounded' ? max(1, (int) round($size * .2)) : 0;
        imagefilledrectangle($im, $left + $radius, $top, $right - $radius, $bottom, $color);
        imagefilledrectangle($im, $left, $top + $radius, $right, $bottom - $radius, $color);
        if ($radius) {
            foreach ([[$left + $radius, $top + $radius], [$right - $radius, $top + $radius],
                [$right - $radius, $bottom - $radius], [$left + $radius, $bottom - $radius]] as [$cx, $cy]) {
                imagefilledellipse($im, $cx, $cy, $radius * 2, $radius * 2, $color);
            }
        }
    }

    private function outlineShape($im, float $x, float $y, float $size, int $color, string $shape): void
    {
        $left = (int) round($x - $size / 2);
        $top = (int) round($y - $size / 2);
        $right = (int) round($x + $size / 2);
        $bottom = (int) round($y + $size / 2);
        if ($shape === 'square') {
            imagerectangle($im, $left, $top, $right, $bottom, $color);

            return;
        }
        $radius = max(1, (int) round($size * .2));
        imageline($im, $left + $radius, $top, $right - $radius, $top, $color);
        imageline($im, $left + $radius, $bottom, $right - $radius, $bottom, $color);
        imageline($im, $left, $top + $radius, $left, $bottom - $radius, $color);
        imageline($im, $right, $top + $radius, $right, $bottom - $radius, $color);
        foreach ([[$left + $radius, $top + $radius, 180, 270], [$right - $radius, $top + $radius, 270, 360],
            [$right - $radius, $bottom - $radius, 0, 90], [$left + $radius, $bottom - $radius, 90, 180]] as [$cx, $cy, $start, $end]) {
            imagearc($im, $cx, $cy, $radius * 2, $radius * 2, $start, $end, $color);
        }
    }

    private function shadePhotoForStamps($image, int $width, int $height, int $centreAlpha): void
    {
        // Keep the photo visible at the edges and increase contrast across the stamp rows.
        for ($y = 0; $y < $height; $y++) {
            $distance = abs(($y + .5) / $height - .5);
            $fade = max(0, min(1, ($distance - .32) / .18));
            $shade = imagecolorallocatealpha($image, 0, 0, 0, (int) ($centreAlpha + (97 - $centreAlpha) * $fade));
            imageline($image, 0, $y, $width - 1, $y, $shade);
        }
    }

    private function cover($dest, $source, int $w, int $h): void
    {
        $ratio = max($w / imagesx($source), $h / imagesy($source));
        $sw = (int) ($w / $ratio);
        $sh = (int) ($h / $ratio);
        imagecopyresampled($dest, $source, 0, 0, (int) ((imagesx($source) - $sw) / 2), (int) ((imagesy($source) - $sh) / 2), $w, $h, $sw, $sh);
    }

    private function rgb(string $hex): array
    {
        $n = hexdec(ltrim($hex, '#'));

        return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
    }

    private function color($im, string $hex): int
    {
        return imagecolorallocate($im, ...$this->rgb($hex));
    }
}
