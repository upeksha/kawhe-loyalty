<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
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
        $account->load(['store', 'customer']);
        $store = $account->store;
        $customer = $account->customer;

        // Ensure 4-char manual entry code exists (e.g. for accounts created before migration or pass never updated)
        if (empty($account->manual_entry_code) && $account->store_id) {
            $account->manual_entry_code = LoyaltyAccount::generateManualEntryCode($account->store_id);
            $account->saveQuietly();
        }

        $programName = $this->programName($store);
        $statusText = $this->statusText($account, $store);
        $frontStatusText = $this->frontStatusText($account, $store);
        $customerDisplayName = $this->frontCustomerName($customer->name ?? $customer->email ?? 'Valued Customer');
        $manualCode = $account->manual_entry_code ?? $this->formatTokenForManualEntry(
            ($account->reward_balance ?? 0) > 0 && $account->redeem_token
                ? $account->redeem_token
                : $account->public_token
        );
        $rewardTarget = max(1, (int) ($store->reward_target ?? 10));

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
                        'value' => $this->generateCircleIndicators($account->stamp_count, $rewardTarget),
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
                        'value' => $this->progressText($account, $store),
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
                        'value' => $this->rewardRuleText($store),
                    ],
                    [
                        'key' => 'verification',
                        'label' => 'Redemption',
                        'value' => $store->require_verification_for_redemption
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
        $backgroundColor = $store->background_color ?? '#1F2937';
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
        $brandColor = $store->brand_color ?? '#8B4513';
        $backgroundColor = $store->background_color ?? '#FBF8F4';
        
        // Create unique temp directory for this pass generation to avoid filename conflicts
        $tempDir = sys_get_temp_dir() . '/apple_wallet_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $assetsAdded = [];
        
        // Use store pass logo if available, otherwise fallback to default
        if ($store->pass_logo_path && StoreAssets::exists($store->pass_logo_path)) {
            $passLogoPath = StoreAssets::localTempPath($store->pass_logo_path, pathinfo($store->pass_logo_path, PATHINFO_EXTENSION) ?: 'img');
            if ($passLogoPath && file_exists($passLogoPath)) {
                // Copy to temp file with exact name (logo.png) so PassGenerator recognizes it
                $tempLogoPath = $tempDir . '/logo.png';
                if (copy($passLogoPath, $tempLogoPath)) {
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
        if ($store->pass_hero_image_path && StoreAssets::exists($store->pass_hero_image_path)) {
            $passHeroPath = StoreAssets::localTempPath($store->pass_hero_image_path, pathinfo($store->pass_hero_image_path, PATHINFO_EXTENSION) ?: 'img');
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
            'has_store_logo' => !empty($store->pass_logo_path),
            'has_store_hero' => !empty($store->pass_hero_image_path),
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
