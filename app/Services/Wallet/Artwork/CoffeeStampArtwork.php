<?php

namespace App\Services\Wallet\Artwork;

/** Bounded rendering for the testing coffee design, independent of customer identity. */
class CoffeeStampArtwork
{
    public function render(int $target, int $count, bool $ready, string $platform): string
    {
        [$width, $height] = match ($platform) {
            'apple' => [1125, 432], 'google' => [1032, 812], 'web' => [720, 470],
            default => throw new \InvalidArgumentException('Unknown artwork platform'),
        };
        $target = max(1, $target);
        $count = max(0, min($target, $count));
        // Keep the approved eight-stamp illustration exactly as it was reviewed on-device.
        if ($target === 8 || ($ready && $count === 0)) {
            $state = $ready && $count === 0 ? 'ready' : (string) $count;

            return file_get_contents(public_path("images/coffee-sample-v1/{$platform}-{$state}.png"));
        }
        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);
        $bg = imagecolorallocate($image, 38, 62, 48);
        $cream = imagecolorallocate($image, 242, 236, 215);
        $line = imagecolorallocate($image, 69, 89, 67);
        imagefill($image, 0, 0, $bg);
        imagesetthickness($image, 2);
        for ($offset = 0; $offset < 90; $offset += 18) {
            $points = [[0, .91], [.17, .68], [.27, .81], [.43, .56], [.55, .75], [.7, .61], [.85, .82], [1, .65]];
            for ($i = 1; $i < count($points); $i++) {
                imageline($image, (int) ($points[$i - 1][0] * $width), (int) ($points[$i - 1][1] * $height + $offset), (int) ($points[$i][0] * $width), (int) ($points[$i][1] * $height + $offset), $line);
            }
        }
        if ($target > 20) {
            $size = min($height * .42, $width * .24);
            $this->cup($image, $width / 2, $height * .32, $size, $cream);
            $x = (int) ($width * .12);
            $y = (int) ($height * .70);
            $barWidth = (int) ($width * .76);
            $barHeight = (int) ($height * .065);
            imagefilledrectangle($image, $x, $y, $x + $barWidth, $y + $barHeight, $bg);
            imagerectangle($image, $x, $y, $x + $barWidth, $y + $barHeight, $cream);
            if ($count > 0) {
                imagefilledrectangle($image, $x + 2, $y + 2, $x + 2 + (int) (($barWidth - 4) * $count / $target), $y + $barHeight - 2, $cream);
            }
        } else {
            $rows = $target <= 5 ? 1 : 2;
            $columns = (int) ceil($target / $rows);
            $size = min($width * .16, $height * .29, ($width * .86) / ($columns * 1.2));
            $gap = $size * .25;
            $top = ($height - ($rows * $size + ($rows - 1) * $gap)) / 2;
            for ($i = 0; $i < $target; $i++) {
                $row = intdiv($i, $columns);
                $col = $i % $columns;
                $items = min($columns, $target - $row * $columns);
                $left = ($width - ($items * $size + ($items - 1) * $gap)) / 2;
                $x = $left + $col * ($size + $gap) + $size / 2;
                $y = $top + $row * ($size + $gap) + $size / 2;
                imagefilledellipse($image, (int) $x, (int) $y, (int) $size, (int) $size, $i < $count ? $cream : $bg);
                imageellipse($image, (int) $x, (int) $y, (int) $size, (int) $size, $cream);
                if ($i < $count) {
                    $this->cup($image, $x, $y, $size * .60, $bg);
                }
            }
        }
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private function cup(\GdImage $image, float $cx, float $cy, float $size, int $color): void
    {
        $x = $cx - $size / 2;
        $y = $cy - $size / 2;
        imagesetthickness($image, max(2, (int) ($size * .04)));
        imagearc($image, (int) ($x + $size * .79), (int) ($y + $size * .54), (int) ($size * .28), (int) ($size * .30), 270, 90, $color);
        imagefilledrectangle($image, (int) ($x + $size * .16), (int) ($y + $size * .36), (int) ($x + $size * .70), (int) ($y + $size * .60), $color);
        imagefilledellipse($image, (int) ($x + $size * .43), (int) ($y + $size * .59), (int) ($size * .54), (int) ($size * .40), $color);
        imageline($image, (int) ($x + $size * .12), (int) ($y + $size * .89), (int) ($x + $size * .76), (int) ($y + $size * .89), $color);
        foreach ([.34, .51] as $steam) {
            imageline($image, (int) ($x + $size * $steam), (int) ($y + $size * .10), (int) ($x + $size * ($steam - .03)), (int) ($y + $size * .21), $color);
            imageline($image, (int) ($x + $size * ($steam - .03)), (int) ($y + $size * .21), (int) ($x + $size * $steam), (int) ($y + $size * .29), $color);
        }
        imagesetthickness($image,2);
    }
}
