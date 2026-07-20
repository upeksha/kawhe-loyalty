<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Services\Wallet\Apple\AppleWalletSerial;
use App\Support\StoreAssets;
use Byte5\PassGenerator;
use Illuminate\Support\Str;

class AppleWalletPassService
{
    /**
     * Generate Apple Wallet pass (.pkpass) for a loyalty account
     *
     * @param LoyaltyAccount $account
     * @return string Raw pkpass binary data
     * @throws \Exception
     */
    public function generatePass(LoyaltyAccount $account): string
    {
        $account->load(['store', 'customer', 'loyaltyProgram']);
        $store = $account->store;
        $program = $account->resolvedProgram() ?? $store;
        $customer = $account->customer;

        // Ensure 4-char manual entry code exists (e.g. for accounts created before migration or pass never updated)
        if (empty($account->manual_entry_code) && $account->store_id) {
            $account->manual_entry_code = LoyaltyAccount::generateManualEntryCode($account->store_id);
            $account->saveQuietly();
        }

        $programName = $this->programName($program);
        $statusText = $this->statusText($account, $program);
        $frontStatusText = $this->frontStatusText($account, $program);
        $customerDisplayName = $this->frontCustomerName($customer->name ?? $customer->email ?? 'Valued Customer');
        $manualCode = $account->manual_entry_code ?? $this->formatTokenForManualEntry(
            ($account->reward_balance ?? 0) > 0 && $account->redeem_token
                ? $account->redeem_token
                : $account->public_token
        );
        $rewardTarget = max(1, (int) ($program->reward_target ?? 10));
        $walletCardStyle = in_array($store->wallet_card_style, Store::WALLET_CARD_STYLES, true)
            ? $store->wallet_card_style
            : Store::WALLET_CARD_STYLE_CLASSIC;

        // Build pass definition
        $passDefinition = [
            'formatVersion' => 1,
            'passTypeIdentifier' => config('passgenerator.pass_type_identifier'),
            'teamIdentifier' => config('passgenerator.team_identifier'),
            'organizationName' => config('passgenerator.organization_name'),
            'description' => 'Kawhe Loyalty Card',
            'serialNumber' => AppleWalletSerial::fromAccount($account),
            'logoText' => $store->name,
            // Apple Wallet Web Service configuration (required for push notifications)
            // Note: Apple automatically appends /v1 to webServiceURL, so we only specify /wallet
            'webServiceURL' => rtrim(config('app.url'), '/') . '/wallet',
            // Use wallet_auth_token as authenticationToken for per-pass security
            // This is separate from public_token for security (QR code contains public_token, not wallet_auth_token)
            'authenticationToken' => $account->wallet_auth_token,
            'barcode' => [
                // Dynamic QR message: LR:{redeem_token} when reward available, else LA:{public_token}
                'message' => ($account->reward_balance ?? 0) > 0 && $account->redeem_token
                    ? 'LR:' . $account->redeem_token
                    : 'LA:' . $account->public_token,
                // Show the manual entry code directly under the QR code
                'altText' => 'Manual code: ' . $manualCode,
                'format' => 'PKBarcodeFormatQR',
                'messageEncoding' => 'utf-8',
            ],
            'storeCard' => [
                'primaryFields' => [
                    [
                        'key' => 'stamps',
                        'label' => ' ',
                        'value' => $this->generateStampIndicators($account, $rewardTarget, $walletCardStyle),
                    ],
                ],
                'secondaryFields' => [
                    [
                        'key' => 'customer',
                        'label' => 'Customer',
                        'value' => $customerDisplayName,
                    ],
                ],
                'auxiliaryFields' => [
                    [
                        'key' => 'status',
                        'label' => 'Status',
                        'value' => $frontStatusText,
                    ],
                ],
                'backFields' => [
                    [
                        'key' => 'program_back',
                        'label' => 'Program',
                        'value' => $programName,
                    ],
                    [
                        'key' => 'progress',
                        'label' => 'Progress',
                        'value' => $this->progressText($account, $program),
                    ],
                    [
                        'key' => 'status_back',
                        'label' => 'Status',
                        'value' => $statusText,
                    ],
                    [
                        'key' => 'manual_entry_code',
                        'label' => 'Manual Code',
                        'value' => $manualCode,
                    ],
                    [
                        'key' => 'reward_rule',
                        'label' => 'How it works',
                        'value' => $this->rewardRuleText($program),
                    ],
                    [
                        'key' => 'verification',
                        'label' => 'Redemption',
                        'value' => ($program->require_verification_for_redemption ?? $store->require_verification_for_redemption)
                            ? 'Email verification is required before rewards can be redeemed.'
                            : 'Rewards can be redeemed without email verification.',
                    ],
                    [
                        'key' => 'scan_instruction',
                        'label' => 'How to use',
                        'value' => 'Show this pass at checkout to collect stamps or redeem rewards.',
                    ],
                    [
                        'key' => 'store_name',
                        'label' => 'Store',
                        'value' => $store->name,
                    ],
                ],
            ],
        ];

        // Add colors from store branding (with fallbacks)
        $backgroundColor = $program->background_color ?? '#1F2937';
        $foregroundColor = $this->bestContrastTextColor($backgroundColor);
        
        $passDefinition['backgroundColor'] = $this->hexToRgb($backgroundColor);
        $passDefinition['foregroundColor'] = $this->hexToRgb($foregroundColor);
        $passDefinition['labelColor'] = $this->hexToRgb($foregroundColor);

        // Initialize pass generator
        // Certificates are automatically loaded from config in constructor
        // Pass ID is optional - we use serial number for identification
        // Set replaceExistent=true to allow regenerating passes (e.g., after stamp updates)
        $passIdentifier = AppleWalletSerial::fromAccount($account);
        $pass = new PassGenerator($passIdentifier, true); // true = replace existing pass
        
        // Set pass definition
        $pass->setPassDefinition($passDefinition);

        // Add assets (images) - addAsset() expects file paths, not file contents
        // Apple Wallet requires specific filenames: logo.png, strip.png, icon.png, background.png
        $assetsPath = resource_path('wallet/apple/default');
        $brandColor = $program->brand_color ?? '#8B4513';
        $backgroundColor = $program->background_color ?? '#FBF8F4';
        
        // Create unique temp directory for this pass generation to avoid filename conflicts
        $tempDir = sys_get_temp_dir() . '/apple_wallet_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $assetsAdded = [];
        
        // Use store pass logo if available, otherwise fallback to default
        if ($program->pass_logo_path && StoreAssets::exists($program->pass_logo_path)) {
            $passLogoPath = StoreAssets::localTempPath($program->pass_logo_path, pathinfo($program->pass_logo_path, PATHINFO_EXTENSION) ?: 'img');
            if ($passLogoPath && file_exists($passLogoPath)) {
                $tempLogoPath = $tempDir . '/logo.png';
                if ($this->createCircularLogoPng($passLogoPath, $tempLogoPath) || copy($passLogoPath, $tempLogoPath)) {
                    $pass->addAsset($tempLogoPath);
                    $assetsAdded[] = 'logo (store)';
                }
            }
        } elseif (file_exists($assetsPath . '/logo.png')) {
            $pass->addAsset($assetsPath . '/logo.png');
            $assetsAdded[] = 'logo (default)';
        } else {
            $tempLogoPath = $tempDir . '/logo.png';
            if ($this->createFallbackPng($tempLogoPath, 320, 100, $brandColor, $backgroundColor)) {
                $pass->addAsset($tempLogoPath);
                $assetsAdded[] = 'logo (fallback)';
            }
        }
        
        // Use store pass hero image if available, otherwise fallback to default strip
        if ($program->pass_hero_image_path && StoreAssets::exists($program->pass_hero_image_path)) {
            $passHeroPath = StoreAssets::localTempPath($program->pass_hero_image_path, pathinfo($program->pass_hero_image_path, PATHINFO_EXTENSION) ?: 'img');
            if ($passHeroPath && file_exists($passHeroPath)) {
                // Copy to temp file with exact name (strip.png) so PassGenerator recognizes it
                $tempStripPath = $tempDir . '/strip.png';
                if (copy($passHeroPath, $tempStripPath)) {
                    $pass->addAsset($tempStripPath);
                    $assetsAdded[] = 'strip (store)';
                }
            }
        } elseif (file_exists($assetsPath . '/strip.png')) {
            $pass->addAsset($assetsPath . '/strip.png');
            $assetsAdded[] = 'strip (default)';
        } else {
            $tempStripPath = $tempDir . '/strip.png';
            if ($this->createFallbackPng($tempStripPath, 750, 246, $brandColor, $backgroundColor)) {
                $pass->addAsset($tempStripPath);
                $assetsAdded[] = 'strip (fallback)';
            }
        }
        
        // Always add icon and background (required by Apple Wallet)
        if (file_exists($assetsPath . '/icon.png')) {
            $pass->addAsset($assetsPath . '/icon.png');
            $assetsAdded[] = 'icon';
        } else {
            $tempIconPath = $tempDir . '/icon.png';
            if ($this->createFallbackPng($tempIconPath, 87, 87, $brandColor, $backgroundColor)) {
                $pass->addAsset($tempIconPath);
                $assetsAdded[] = 'icon (fallback)';
            }
        }
        if (file_exists($assetsPath . '/background.png')) {
            $pass->addAsset($assetsPath . '/background.png');
            $assetsAdded[] = 'background';
        } else {
            $tempBgPath = $tempDir . '/background.png';
            if ($this->createFallbackPng($tempBgPath, 360, 440, $brandColor, $backgroundColor)) {
                $pass->addAsset($tempBgPath);
                $assetsAdded[] = 'background (fallback)';
            }
        }
        
        // Log which assets were added for debugging
        \Log::info('Apple Wallet: Assets added', [
            'account_id' => $account->id,
            'store_id' => $store->id,
            'assets' => $assetsAdded,
            'has_store_logo' => !empty($program->pass_logo_path),
            'has_store_hero' => !empty($program->pass_hero_image_path),
        ]);
        
        // Clean up temp directory after pass generation
        // Note: We don't delete immediately as PassGenerator may still need the files during create()
        register_shutdown_function(function() use ($tempDir) {
            if (is_dir($tempDir)) {
                array_map('unlink', glob("$tempDir/*"));
                @rmdir($tempDir);
            }
        });

        // Generate and return pkpass binary
        return $pass->create();
    }


    /**
     * Convert hex color to RGB format for Apple Wallet
     *
     * @param string $hex
     * @return string
     */
    protected function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return sprintf('rgb(%d,%d,%d)', $r, $g, $b);
    }

    /**
     * Generate circle indicators for stamp progress
     * Example: "●●●○○" for 3 stamps out of 5
     *
     * @param int $stampCount Current stamp count
     * @param int $rewardTarget Target stamps needed
     * @return string Circle indicators string
     */
    protected function generateCircleIndicators(int $stampCount, int $rewardTarget): string
    {
        // Clamp stamp count to valid range (0 to reward_target)
        $filled = max(0, min($stampCount, $rewardTarget));
        $empty = $rewardTarget - $filled;
        
        // Unicode circles: filled = ● (U+25CF), empty = ○ (U+25CB)
        return str_repeat('●', $filled) . str_repeat('○', $empty);
    }

    protected function generateStampIndicators(LoyaltyAccount $account, int $rewardTarget, string $walletCardStyle): string
    {
        if ($walletCardStyle !== Store::WALLET_CARD_STYLE_ABSTRACT) {
            return $this->generateCircleIndicators((int) $account->stamp_count, $rewardTarget);
        }

        $filled = max(0, min((int) $account->stamp_count, $rewardTarget));
        $rewardReady = (int) ($account->reward_balance ?? 0) > 0;
        $icons = [];

        for ($index = 1; $index <= $rewardTarget; $index++) {
            if ($index === $rewardTarget) {
                $icons[] = '🎁';
                continue;
            }

            $icons[] = $rewardReady || $index <= $filled ? '☕' : '○';
        }

        return implode(' ', $icons);
    }

    /**
     * Convert an uploaded logo into a circular transparent PNG so Apple Wallet
     * renders it consistently even when the merchant uploads a square asset.
     */
    protected function createCircularLogoPng(string $sourcePath, string $destinationPath, int $size = 160): bool
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecreatefromstring')) {
            return false;
        }

        $raw = @file_get_contents($sourcePath);
        if ($raw === false) {
            return false;
        }

        $source = @imagecreatefromstring($raw);
        if (!$source) {
            return false;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);
            return false;
        }

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        $scale = max($size / $sourceWidth, $size / $sourceHeight);
        $resizedWidth = (int) ceil($sourceWidth * $scale);
        $resizedHeight = (int) ceil($sourceHeight * $scale);
        $destinationX = (int) floor(($size - $resizedWidth) / 2);
        $destinationY = (int) floor(($size - $resizedHeight) / 2);

        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas,
            $source,
            $destinationX,
            $destinationY,
            0,
            0,
            $resizedWidth,
            $resizedHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagealphablending($canvas, false);
        $radius = $size / 2;
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $dx = $x - $radius + 0.5;
                $dy = $y - $radius + 0.5;
                if (($dx * $dx) + ($dy * $dy) > ($radius * $radius)) {
                    imagesetpixel($canvas, $x, $y, $transparent);
                }
            }
        }

        $written = imagepng($canvas, $destinationPath);

        imagedestroy($source);
        imagedestroy($canvas);

        return (bool) $written;
    }

    /**
     * Format token for manual entry (adds dashes for readability).
     * Works for any length; 16-char example: "abcd1234efgh5678" -> "abcd-1234-efgh-5678".
     *
     * @param string $token The token to format
     * @return string Formatted token with dashes every 4 characters
     */
    protected function formatTokenForManualEntry(string $token): string
    {
        return implode('-', str_split($token, 4));
    }

    protected function programName($store): string
    {
        return trim((string) ($store->reward_title ?? 'Rewards')) ?: 'Rewards';
    }

    protected function progressText(LoyaltyAccount $account, $store): string
    {
        $rewardTarget = max(1, (int) ($store->reward_target ?? 10));
        $stampCount = max(0, min((int) $account->stamp_count, $rewardTarget));

        return sprintf('Stamps %d/%d', $stampCount, $rewardTarget);
    }

    protected function statusText(LoyaltyAccount $account, $store): string
    {
        $rewardBalance = (int) ($account->reward_balance ?? 0);

        if ($rewardBalance > 1) {
            return sprintf('%d rewards available', $rewardBalance);
        }

        if ($rewardBalance === 1) {
            return 'Reward ready';
        }

        return $this->progressText($account, $store);
    }

    protected function frontStatusText(LoyaltyAccount $account, $store): string
    {
        $rewardBalance = (int) ($account->reward_balance ?? 0);

        if ($rewardBalance > 1) {
            return sprintf('%d rewards', $rewardBalance);
        }

        if ($rewardBalance === 1) {
            return 'Ready';
        }

        $rewardTarget = max(1, (int) ($store->reward_target ?? 10));
        $stampCount = max(0, min((int) $account->stamp_count, $rewardTarget));

        return sprintf('%d/%d stamps', $stampCount, $rewardTarget);
    }

    protected function frontCustomerName(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?: 'Valued Customer';

        return Str::limit($normalized, 18, '…');
    }

    protected function rewardRuleText($store): string
    {
        $rewardTarget = max(1, (int) ($store->reward_target ?? 10));

        return sprintf(
            'Collect %d stamps to earn %s.',
            $rewardTarget,
            $this->programName($store)
        );
    }

    protected function bestContrastTextColor(string $backgroundHex): string
    {
        [$r, $g, $b] = $this->hexToRgbArray($backgroundHex);
        $luminance = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

        return $luminance > 146 ? '#111111' : '#FFFFFF';
    }

    /**
     * Create a simple fallback PNG asset if defaults are missing.
     * This prevents invalid pkpass files when required images aren't present.
     */
    protected function createFallbackPng(string $path, int $width, int $height, string $badgeHex, string $bgHex): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            \Log::warning('Apple Wallet: GD not available for fallback images', [
                'path' => $path,
            ]);
            return false;
        }

        $img = imagecreatetruecolor($width, $height);
        if (! $img) {
            return false;
        }

        [$bgR, $bgG, $bgB] = $this->hexToRgbArray($bgHex);
        [$badgeR, $badgeG, $badgeB] = $this->hexToRgbArray($badgeHex);

        $bgColor = imagecolorallocate($img, $bgR, $bgG, $bgB);
        $badgeColor = imagecolorallocate($img, $badgeR, $badgeG, $badgeB);

        imagefilledrectangle($img, 0, 0, $width, $height, $bgColor);
        $diameter = (int) (min($width, $height) * 0.7);
        imagefilledellipse($img, (int) ($width / 2), (int) ($height / 2), $diameter, $diameter, $badgeColor);

        $saved = imagepng($img, $path);
        imagedestroy($img);

        return $saved && file_exists($path);
    }

    /**
     * Convert hex color to RGB array for GD.
     */
    protected function hexToRgbArray(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
