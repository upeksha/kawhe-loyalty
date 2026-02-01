<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\Apple\AppleWalletSerial;
use Byte5\PassGenerator;
use Illuminate\Support\Facades\Storage;

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
                'altText' => 'Manual Code: ' . ($account->manual_entry_code ?? $this->formatTokenForManualEntry(
                    ($account->reward_balance ?? 0) > 0 && $account->redeem_token
                        ? $account->redeem_token
                        : $account->public_token
                )),
                'format' => 'PKBarcodeFormatQR',
                'messageEncoding' => 'utf-8',
            ],
            'storeCard' => [
                'primaryFields' => [
                    [
                        'key' => 'stamps',
                        'label' => ' ',
                        'value' => $this->generateCircleIndicators($account->stamp_count, $store->reward_target ?? 10),
                    ],
                ],
                // Left column: Customer. Right column: Rewards (when available).
                'secondaryFields' => [
                    [
                        'key' => 'customer',
                        'label' => 'Customer',
                        'value' => $customer->name ?? $customer->email ?? 'Valued Customer',
                    ],
                    ...(($account->reward_balance ?? 0) > 0 ? [[
                        'key' => 'reward_indicator',
                        'label' => ' ',
                        'value' => '🎁 ' . (string) ($account->reward_balance ?? 0),
                    ]] : []),
                ],
                'auxiliaryFields' => [],
                'backFields' => [
                    [
                        'key' => 'manual_entry_title',
                        'label' => 'Manual Entry',
                        'value' => 'If QR code cannot be scanned, enter this code manually:',
                    ],
                    [
                        'key' => 'manual_entry_code',
                        'label' => 'Manual Code',
                        'value' => $account->manual_entry_code ?? $this->formatTokenForManualEntry(
                            ($account->reward_balance ?? 0) > 0 && $account->redeem_token
                                ? $account->redeem_token
                                : $account->public_token
                        ),
                    ],
                    [
                        'key' => 'manual_entry_instruction',
                        'label' => 'How to Use',
                        'value' => ($account->reward_balance ?? 0) > 0 && $account->redeem_token
                            ? 'Enter this code in the scanner if QR code cannot be read.'
                            : 'Enter this code in the scanner if QR code cannot be read.',
                    ],
                    [
                        'key' => 'support',
                        'label' => 'Support',
                        'value' => 'support@kawhe.shop',
                    ],
                    [
                        'key' => 'terms',
                        'label' => 'Terms',
                        'value' => 'Show this pass at checkout to collect stamps.',
                    ],
                ],
            ],
        ];

        // Add colors from store branding (with fallbacks)
        $backgroundColor = $store->background_color ?? '#1F2937';
        $foregroundColor = $store->brand_color ?? '#FFFFFF';
        
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
        
        // Create unique temp directory for this pass generation to avoid filename conflicts
        $tempDir = sys_get_temp_dir() . '/apple_wallet_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $assetsAdded = [];
        
        // Use store pass logo if available, otherwise fallback to default
        if ($store->pass_logo_path && Storage::disk('public')->exists($store->pass_logo_path)) {
            $passLogoPath = Storage::disk('public')->path($store->pass_logo_path);
            if (file_exists($passLogoPath)) {
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
        }
        
        // Use store pass hero image if available, otherwise fallback to default strip
        if ($store->pass_hero_image_path && Storage::disk('public')->exists($store->pass_hero_image_path)) {
            $passHeroPath = Storage::disk('public')->path($store->pass_hero_image_path);
            if (file_exists($passHeroPath)) {
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
        }
        
        // Always add icon and background (required by Apple Wallet)
        if (file_exists($assetsPath . '/icon.png')) {
            $pass->addAsset($assetsPath . '/icon.png');
            $assetsAdded[] = 'icon';
        }
        if (file_exists($assetsPath . '/background.png')) {
            $pass->addAsset($assetsPath . '/background.png');
            $assetsAdded[] = 'background';
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
}
