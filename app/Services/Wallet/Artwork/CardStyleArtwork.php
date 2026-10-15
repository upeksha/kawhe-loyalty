<?php

namespace App\Services\Wallet\Artwork;

/** Shared artwork for web and both Wallets. No customer identifiers are embedded. */
class CardStyleArtwork
{
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
        $ink = $this->color($im, $options['ink']);
        imagefill($im, 0, 0, $bg);
        if ($options['style'] === 'photo' && $options['photo']) {
            $photo = imagecreatefromstring($options['photo']);
            if ($photo) {
                $this->cover($im, $photo, $w, $h);
                imagedestroy($photo);
                imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocatealpha($im, 0, 0, 0, 44));
            }
        }
        if ($options['style'] === 'illustrated') {
            $line = imagecolorallocatealpha($im, ...[...$this->rgb($options['ink']), 108]);
            imagesetthickness($im, 2);
            if ($options['pattern'] === 'waves') {
                for ($y = -$h; $y < $h * 2; $y += 42) {
                    imagearc($im, (int) ($w * .25), $y, (int) ($w * 1.6), (int) ($h * .7), 0, 180, $line);
                }
            } elseif ($options['pattern'] === 'botanical') {
                for ($side = 0; $side < 2; $side++) {
                    $x = $side ? $w - 50 : 50;
                    imageline($im, $x, $h, $x, 0, $line);
                    for ($y = 35; $y < $h; $y += 65) {
                        imageellipse($im, $x - 16, $y, 32, 62, $line);
                        imageellipse($im, $x + 16, $y + 24, 32, 62, $line);
                    }
                }
            } else {
                for ($o = 0; $o < 95; $o += 18) {
                    $pts = [[0, .9], [.18, .65], [.3, .82], [.48, .55], [.7, .84], [.82, .68], [1, .9]];
                    for ($i = 1; $i < count($pts); $i++) {
                        imageline($im, (int) ($pts[$i - 1][0] * $w), (int) ($pts[$i - 1][1] * $h + $o), (int) ($pts[$i][0] * $w), (int) ($pts[$i][1] * $h + $o), $line);
                    }
                }
            }
        }
        $custom = $options['icon'] === 'custom' && $options['custom_icon'] ? imagecreatefromstring($options['custom_icon']) : null;
        if ($ready && $count === 0) {
            $size = min($h * .63, $w * .3);
            $this->stamp($im, $w / 2, $h / 2, $size, true, $bg, $ink, $options['icon'], $custom);
            for ($i = 0; $i < 8; $i++) {
                $a = $i * M_PI / 4;
                imageline($im, (int) ($w / 2 + cos($a) * $size * .68), (int) ($h / 2 + sin($a) * $size * .68), (int) ($w / 2 + cos($a) * $size * .8), (int) ($h / 2 + sin($a) * $size * .8), $ink);
            }
        } elseif ($target > 20) {
            $this->stamp($im, $w / 2, $h * .34, min($h * .4, $w * .24), true, $bg, $ink, $options['icon'], $custom);
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
            $size = min($w * .16, $h * .29, $w * .86 / ($cols * 1.2));
            $gap = $size * .25;
            $top = ($h - ($rows * $size + ($rows - 1) * $gap)) / 2;
            for ($i = 0; $i < $target; $i++) {
                $row = intdiv($i, $cols);
                $col = $i % $cols;
                $items = min($cols, $target - $row * $cols);
                $left = ($w - ($items * $size + ($items - 1) * $gap)) / 2;
                $this->stamp($im, $left + $col * ($size + $gap) + $size / 2, $top + $row * ($size + $gap) + $size / 2, $size, $i < $count, $bg, $ink, $options['icon'], $custom);
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

    private function stamp($im, float $x, float $y, float $size, bool $filled, int $bg, int $ink, string $icon, $custom): void
    {
        imagesetthickness($im, max(2, (int) ($size * .02)));
        if (! $filled) {
            imageellipse($im, (int) $x, (int) $y, (int) $size, (int) $size, $ink);

            return;
        }
        imagefilledellipse($im, (int) $x, (int) $y, (int) $size, (int) $size, $custom ? imagecolorallocate($im, 255, 255, 255) : $ink);
        $s = $size * .54;
        if ($custom) {
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

    private function cover($dest, $source, int $w, int $h): void
    {
        $ratio = max($w / imagesx($source), $h / imagesy($source));
        $sw = (int) ($w / $ratio);
        $sh = (int) ($h / $ratio);
        imagecopyresampled($dest, $source, 0, 0, (int) ((imagesx($source) - $sw) / 2), (int) ((imagesy($source) - $sh) / 2), $w, $h, $sw, $sh);
    }

    private function rgb(string $hex): array
    {
        $n = hexdec(ltrim($hex,'#'));

        return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
    }

    private function color($im,string $hex): int
    {
        return imagecolorallocate($im,...$this->rgb($hex));
    }
}
