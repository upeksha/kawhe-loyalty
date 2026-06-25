<?php

namespace App\Support;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;

/**
 * Resolves program/store colors into accessible customer-facing theme tokens.
 */
final class ProgramBranding
{
    public const DEFAULT_BACKGROUND = '#1F2937';

    public const DEFAULT_BRAND = '#3d7659';

    public static function resolve(?LoyaltyProgram $program = null, ?Store $store = null): ProgramTheme
    {
        $brand = self::normalizeBrand(
            $program?->brand_color
            ?? $store?->brand_color
            ?? self::DEFAULT_BRAND
        );

        $background = self::normalizeBackground(
            $program?->background_color
            ?? $store?->background_color
            ?? self::DEFAULT_BACKGROUND
        );

        return self::fromColors($brand, $background);
    }

    public static function resolveFromAccount(LoyaltyAccount $account): ProgramTheme
    {
        $program = $account->loyaltyProgram;
        $store = $account->store;

        $brand = $account->program_brand_color
            ?? $program?->brand_color
            ?? $store?->brand_color
            ?? self::DEFAULT_BRAND;

        $background = $account->program_background_color
            ?? $program?->background_color
            ?? $store?->background_color
            ?? self::DEFAULT_BACKGROUND;

        return self::fromColors($brand, $background);
    }

    public static function fromColors(string $brand, string $background): ProgramTheme
    {
        $brand = self::normalizeBrand($brand);
        $background = self::normalizeBackground($background);

        $bgRgb = self::hexToRgb($background);
        $simpleLum = $bgRgb ? self::simpleLuminance($bgRgb) : 0.0;

        if ($bgRgb) {
            $textOnBg = $simpleLum < 0.5 ? '#ffffff' : '#111827';
            $mutedOnBg = $simpleLum < 0.5 ? 'rgba(255,255,255,0.85)' : 'rgba(17,24,39,0.75)';
        } else {
            $textOnBg = '#ffffff';
            $mutedOnBg = 'rgba(255,255,255,0.85)';
        }

        $cardMutedOnBg = $bgRgb
            ? ($simpleLum < 0.5 ? 'rgba(255,255,255,0.65)' : 'rgba(17,24,39,0.6)')
            : 'rgba(255,255,255,0.65)';

        if ($bgRgb) {
            [$r, $g, $b] = $bgRgb;
            if ($simpleLum < 0.5) {
                $loyaltySurface = sprintf('#%02X%02X%02X', min(255, $r + 28), min(255, $g + 28), min(255, $b + 28));
                $loyaltyInner = sprintf('#%02X%02X%02X', min(255, $r + 18), min(255, $g + 18), min(255, $b + 18));
                $loyaltyDivider = 'rgba(255,255,255,0.09)';
            } else {
                $loyaltySurface = sprintf('#%02X%02X%02X', max(0, $r - 18), max(0, $g - 18), max(0, $b - 18));
                $loyaltyInner = sprintf('#%02X%02X%02X', max(0, $r - 10), max(0, $g - 10), max(0, $b - 10));
                $loyaltyDivider = 'rgba(0,0,0,0.08)';
            }
        } else {
            $loyaltySurface = '#374151';
            $loyaltyInner = '#2D3748';
            $loyaltyDivider = 'rgba(255,255,255,0.09)';
        }

        $loyaltyCardBg = 'linear-gradient(135deg, '.$loyaltySurface.' 0%, '.$background.' 50%, '.$loyaltyInner.' 100%)';
        $walletSheetBg = 'linear-gradient(160deg, '.$loyaltySurface.' 0%, '.$background.' 100%)';

        $joinCardTop = self::mix($background, '#111827', $simpleLum > 0.72 ? 0.72 : 0.46);
        $joinCardBottom = self::mix($background, '#020617', $simpleLum > 0.72 ? 0.84 : 0.62);
        $joinCardBg = 'linear-gradient(145deg, '.$joinCardTop.', '.$joinCardBottom.')';
        $joinCardLum = self::luminance($joinCardTop);
        $joinCardIsDark = $joinCardLum < 0.54;

        $joinCardText = $joinCardIsDark ? '#F8FAFC' : '#111827';
        $joinCardMuted = $joinCardIsDark ? 'rgba(248,250,252,0.78)' : '#4B5563';
        $joinCardStrong = $joinCardIsDark ? '#FFFFFF' : '#111827';
        $joinCardLabel = $joinCardIsDark ? 'rgba(248,250,252,0.88)' : '#374151';
        $joinInputBg = $joinCardIsDark
            ? self::mix($joinCardBottom, '#0F172A', 0.42)
            : '#FFFFFF';
        $joinInputText = $joinCardIsDark ? '#F8FAFC' : '#111827';
        $joinInputBorder = $joinCardIsDark
            ? self::mix($joinCardBottom, '#64748B', 0.55)
            : '#D1D5DB';
        $joinInputPlaceholder = $joinCardIsDark ? 'rgba(248,250,252,0.55)' : '#9CA3AF';
        $dividerColor = $joinCardIsDark ? 'rgba(255,255,255,0.12)' : '#E2E8F0';

        $textOnBrand = self::contrastRatio($brand, '#111827') > self::contrastRatio($brand, '#FFFFFF')
            ? '#111827'
            : '#FFFFFF';

        $brandCardContrast = self::contrastRatio($brand, $joinCardTop);
        $secondaryBorder = $brandCardContrast >= 3 ? $brand : 'rgba(248,250,252,0.24)';
        $secondaryText = $brandCardContrast >= 3 ? $brand : '#F8FAFC';
        $secondaryHoverBg = $brandCardContrast >= 3 ? $brand : 'rgba(248,250,252,0.10)';
        $secondaryHoverText = $brandCardContrast >= 3 ? $textOnBrand : '#FFFFFF';

        $brandRgb = self::hexToRgb($brand);
        $brandSimpleLum = $brandRgb ? self::simpleLuminance($brandRgb) : 0.5;
        $brandIsVeryLight = $brandSimpleLum > 0.9;
        $brandFocusRing = $brand.'40';
        $brandMutedBg = $brand.'22';
        $brandGlow28 = $brand.'28';
        $brandGlow20 = $brand.'20';
        $brandGlow15 = $brand.'15';
        $brandGlow25 = $brand.'25';
        $brandBorder44 = $brand.'44';
        $brandBorder88 = $brand.'88';
        $statusCardBg = $brandIsVeryLight ? 'rgba(17,24,39,0.92)' : 'rgba(255,255,255,0.97)';
        $statusCardText = $brandIsVeryLight ? '#F8FAFC' : '#111827';
        $statusCardMuted = $brandIsVeryLight ? 'rgba(248,250,252,0.76)' : '#4B5563';
        $statusCardSoft = $brandIsVeryLight ? 'rgba(248,250,252,0.62)' : '#6B7280';
        $statusOutlineBorder = $brandIsVeryLight ? 'rgba(255,255,255,0.22)' : $brand;
        $statusOutlineText = $brandIsVeryLight ? '#F8FAFC' : $brand;
        $statusOutlineHoverBg = $brandIsVeryLight ? 'rgba(255,255,255,0.08)' : $brand;
        $statusOutlineHoverText = $brandIsVeryLight ? '#FFFFFF' : $textOnBrand;

        return new ProgramTheme(
            bg: $background,
            brand: $brand,
            brandFocusRing: $brandFocusRing,
            brandMutedBg: $brandMutedBg,
            brandGlow28: $brandGlow28,
            brandGlow20: $brandGlow20,
            brandGlow15: $brandGlow15,
            brandGlow25: $brandGlow25,
            brandBorder44: $brandBorder44,
            brandBorder88: $brandBorder88,
            textOnBg: $textOnBg,
            mutedOnBg: $mutedOnBg,
            cardMutedOnBg: $cardMutedOnBg,
            loyaltySurface: $loyaltySurface,
            loyaltyInner: $loyaltyInner,
            loyaltyDivider: $loyaltyDivider,
            loyaltyCardBg: $loyaltyCardBg,
            walletSheetBg: $walletSheetBg,
            joinCardBg: $joinCardBg,
            joinCardText: $joinCardText,
            joinCardMuted: $joinCardMuted,
            joinCardStrong: $joinCardStrong,
            joinCardLabel: $joinCardLabel,
            joinInputBg: $joinInputBg,
            joinInputText: $joinInputText,
            joinInputBorder: $joinInputBorder,
            joinInputPlaceholder: $joinInputPlaceholder,
            textOnBrand: $textOnBrand,
            secondaryBorder: $secondaryBorder,
            secondaryText: $secondaryText,
            secondaryHoverBg: $secondaryHoverBg,
            secondaryHoverText: $secondaryHoverText,
            dividerColor: $dividerColor,
            statusCardBg: $statusCardBg,
            statusCardText: $statusCardText,
            statusCardMuted: $statusCardMuted,
            statusCardSoft: $statusCardSoft,
            statusOutlineBorder: $statusOutlineBorder,
            statusOutlineText: $statusOutlineText,
            statusOutlineHoverBg: $statusOutlineHoverBg,
            statusOutlineHoverText: $statusOutlineHoverText,
        );
    }

    public static function cardTitle(?LoyaltyProgram $program, ?Store $store): string
    {
        if ($program) {
            return $program->name ?: ($program->reward_title ?: ($store?->name ?? config('app.name', 'Kawhe')));
        }

        return $store?->name ?? config('app.name', 'Kawhe');
    }

    public static function cardSubtitle(?LoyaltyProgram $program, ?Store $store): ?string
    {
        if (! $program || ! $store) {
            return null;
        }

        $title = self::cardTitle($program, $store);

        return $title !== $store->name ? $store->name : null;
    }

    private static function normalizeBrand(?string $color): string
    {
        return self::normalizeHex($color, self::DEFAULT_BRAND);
    }

    private static function normalizeBackground(?string $color): string
    {
        return self::normalizeHex($color, self::DEFAULT_BACKGROUND);
    }

    private static function normalizeHex(?string $color, string $fallback): string
    {
        $color = trim((string) $color);
        if ($color === '') {
            return $fallback;
        }

        if ($color[0] !== '#') {
            $color = '#'.$color;
        }

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
            return $fallback;
        }

        return strtoupper($color);
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function hexToRgb(string $hex): ?array
    {
        $cleaned = ltrim($hex, '#');
        if (strlen($cleaned) !== 6) {
            return null;
        }

        return [
            hexdec(substr($cleaned, 0, 2)),
            hexdec(substr($cleaned, 2, 2)),
            hexdec(substr($cleaned, 4, 2)),
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private static function simpleLuminance(array $rgb): float
    {
        return ((0.299 * $rgb[0]) + (0.587 * $rgb[1]) + (0.114 * $rgb[2])) / 255;
    }

    private static function luminance(string $hex): float
    {
        $rgb = self::hexToRgb($hex);
        if (! $rgb) {
            return 0.0;
        }

        $convert = static function (int $channel): float {
            $normalized = $channel / 255;

            return $normalized <= 0.03928
                ? $normalized / 12.92
                : (($normalized + 0.055) / 1.055) ** 2.4;
        };

        return (0.2126 * $convert($rgb[0])) + (0.7152 * $convert($rgb[1])) + (0.0722 * $convert($rgb[2]));
    }

    private static function mix(string $from, string $to, float $ratio): string
    {
        $fromRgb = self::hexToRgb($from);
        $toRgb = self::hexToRgb($to);

        if (! $fromRgb || ! $toRgb) {
            return $from;
        }

        $ratio = max(0, min(1, $ratio));

        return sprintf(
            '#%02X%02X%02X',
            (int) round($fromRgb[0] + (($toRgb[0] - $fromRgb[0]) * $ratio)),
            (int) round($fromRgb[1] + (($toRgb[1] - $fromRgb[1]) * $ratio)),
            (int) round($fromRgb[2] + (($toRgb[2] - $fromRgb[2]) * $ratio))
        );
    }

    private static function contrastRatio(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}

final class ProgramTheme
{
    public function __construct(
        public readonly string $bg,
        public readonly string $brand,
        public readonly string $brandFocusRing,
        public readonly string $brandMutedBg,
        public readonly string $brandGlow28,
        public readonly string $brandGlow20,
        public readonly string $brandGlow15,
        public readonly string $brandGlow25,
        public readonly string $brandBorder44,
        public readonly string $brandBorder88,
        public readonly string $textOnBg,
        public readonly string $mutedOnBg,
        public readonly string $cardMutedOnBg,
        public readonly string $loyaltySurface,
        public readonly string $loyaltyInner,
        public readonly string $loyaltyDivider,
        public readonly string $loyaltyCardBg,
        public readonly string $walletSheetBg,
        public readonly string $joinCardBg,
        public readonly string $joinCardText,
        public readonly string $joinCardMuted,
        public readonly string $joinCardStrong,
        public readonly string $joinCardLabel,
        public readonly string $joinInputBg,
        public readonly string $joinInputText,
        public readonly string $joinInputBorder,
        public readonly string $joinInputPlaceholder,
        public readonly string $textOnBrand,
        public readonly string $secondaryBorder,
        public readonly string $secondaryText,
        public readonly string $secondaryHoverBg,
        public readonly string $secondaryHoverText,
        public readonly string $dividerColor,
        public readonly string $statusCardBg,
        public readonly string $statusCardText,
        public readonly string $statusCardMuted,
        public readonly string $statusCardSoft,
        public readonly string $statusOutlineBorder,
        public readonly string $statusOutlineText,
        public readonly string $statusOutlineHoverBg,
        public readonly string $statusOutlineHoverText,
    ) {}

    /**
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        return [
            'bg' => $this->bg,
            'brand' => $this->brand,
            'brand-focus' => $this->brandFocusRing,
            'brand-muted-bg' => $this->brandMutedBg,
            'brand-glow-28' => $this->brandGlow28,
            'brand-glow-20' => $this->brandGlow20,
            'brand-glow-15' => $this->brandGlow15,
            'brand-glow-25' => $this->brandGlow25,
            'brand-border-44' => $this->brandBorder44,
            'brand-border-88' => $this->brandBorder88,
            'text-on-bg' => $this->textOnBg,
            'muted-on-bg' => $this->mutedOnBg,
            'card-muted-on-bg' => $this->cardMutedOnBg,
            'loyalty-surface' => $this->loyaltySurface,
            'loyalty-inner' => $this->loyaltyInner,
            'loyalty-divider' => $this->loyaltyDivider,
            'loyalty-card-bg' => $this->loyaltyCardBg,
            'wallet-sheet-bg' => $this->walletSheetBg,
            'card-bg' => $this->joinCardBg,
            'card-text' => $this->joinCardText,
            'card-muted' => $this->joinCardMuted,
            'card-strong' => $this->joinCardStrong,
            'card-label' => $this->joinCardLabel,
            'input-bg' => $this->joinInputBg,
            'input-text' => $this->joinInputText,
            'input-border' => $this->joinInputBorder,
            'input-placeholder' => $this->joinInputPlaceholder,
            'text-on-brand' => $this->textOnBrand,
            'secondary-border' => $this->secondaryBorder,
            'secondary-text' => $this->secondaryText,
            'secondary-hover-bg' => $this->secondaryHoverBg,
            'secondary-hover-text' => $this->secondaryHoverText,
            'divider' => $this->dividerColor,
            'status-card-bg' => $this->statusCardBg,
            'status-card-text' => $this->statusCardText,
            'status-card-muted' => $this->statusCardMuted,
            'status-card-soft' => $this->statusCardSoft,
            'status-outline-border' => $this->statusOutlineBorder,
            'status-outline-text' => $this->statusOutlineText,
            'status-outline-hover-bg' => $this->statusOutlineHoverBg,
            'status-outline-hover-text' => $this->statusOutlineHoverText,
        ];
    }

    public function cssVariableBlock(string $selector = '.customer-page'): string
    {
        $declarations = collect($this->cssVariables())
            ->map(fn (string $value, string $key) => "--program-{$key}: {$value};")
            ->implode(' ');

        return "{$selector} { {$declarations} }";
    }
}
