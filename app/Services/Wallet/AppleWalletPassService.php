<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\Apple\AppleWalletSerial;
use App\Services\Wallet\Artwork\WalletArtworkService;
use App\Support\StoreAssets;
use Byte5\PassGenerator;
use Illuminate\Support\Str;

class AppleWalletPassService
{
    /**
     * Generate Apple Wallet pass (.pkpass) for a loyalty account
     *
     * @return string Raw pkpass binary data
     *
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
            'webServiceURL' => rtrim(config('app.url'), '/').'/wallet',
            // Use wallet_auth_token as authenticationToken for per-pass security
            // This is separate from public_token for security (QR code contains public_token, not wallet_auth_token)
            'authenticationToken' => $account->wallet_auth_token,
            'barcode' => [
                // Dynamic QR message: LR:{redeem_token} when reward available, else LA:{public_token}
                'message' => ($account->reward_balance ?? 0) > 0 && $account->redeem_token
                    ? 'LR:'.$account->redeem_token
                    : 'LA:'.$account->public_token,
                // Show the manual entry code directly under the QR code
                'altText' => 'Manual code: '.$manualCode,
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

        // Apple Wallet assets use immutable platform-specific derivatives.
        $assetsPath = resource_path('wallet/apple/default');
        $brandColor = $program->brand_color ?? '#8B4513';
        $backgroundColor = $program->background_color ?? '#FBF8F4';

        $tempDir = sys_get_temp_dir().'/apple_wallet_'.uniqid('', true);
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new \RuntimeException('Unable to create the Apple Wallet asset workspace.');
        }

        $assetsAdded = [];

        try {
            try {
                $artwork = app(WalletArtworkService::class)->syncForProgram($program, false);
                $appleManifest = $artwork->manifest['apple'] ?? [];
            } catch (\Throwable $exception) {
                \Log::warning('Apple Wallet: Generated artwork unavailable, using fallbacks', [
                    'account_id' => $account->id,
                    'program_id' => $program->id ?? null,
                    'error_type' => class_basename($exception),
                ]);
                $appleManifest = [];
            }

            $assetNames = [
                'logo' => 'logo.png',
                'logo_2x' => 'logo@2x.png',
                'logo_3x' => 'logo@3x.png',
                'strip' => 'strip.png',
                'strip_2x' => 'strip@2x.png',
                'strip_3x' => 'strip@3x.png',
            ];
            foreach ($assetNames as $manifestKey => $fileName) {
                $relativePath = $appleManifest[$manifestKey] ?? null;
                $contents = $relativePath ? StoreAssets::get($relativePath) : null;
                if ($contents === null) {
                    continue;
                }

                $localPath = $tempDir.'/'.$fileName;
                file_put_contents($localPath, $contents);
                $pass->addAsset($localPath);
                $assetsAdded[] = $fileName;
            }

            if (! in_array('logo.png', $assetsAdded, true)) {
                $fallbackLogo = $tempDir.'/logo.png';
                $this->createFallbackPng($fallbackLogo, 160, 50, $brandColor, $backgroundColor);
                $pass->addAsset($fallbackLogo);
                $assetsAdded[] = 'logo.png (fallback)';
            }
            if (! in_array('strip.png', $assetsAdded, true)) {
                $fallbackStrip = $tempDir.'/strip.png';
                $this->createFallbackPng($fallbackStrip, 375, 144, $brandColor, $backgroundColor);
                $pass->addAsset($fallbackStrip);
                $assetsAdded[] = 'strip.png (fallback)';
            }

            if (file_exists($assetsPath.'/icon.png')) {
                $pass->addAsset($assetsPath.'/icon.png');
                $assetsAdded[] = 'icon.png';
            } else {
                $tempIconPath = $tempDir.'/icon.png';
                if ($this->createFallbackPng($tempIconPath, 87, 87, $brandColor, $backgroundColor)) {
                    $pass->addAsset($tempIconPath);
                    $assetsAdded[] = 'icon.png (fallback)';
                }
            }

            if (file_exists($assetsPath.'/background.png')) {
                $pass->addAsset($assetsPath.'/background.png');
                $assetsAdded[] = 'background.png';
            } else {
                $tempBgPath = $tempDir.'/background.png';
                if ($this->createFallbackPng($tempBgPath, 360, 440, $brandColor, $backgroundColor)) {
                    $pass->addAsset($tempBgPath);
                    $assetsAdded[] = 'background.png (fallback)';
                }
            }

            \Log::info('Apple Wallet: Assets added', [
                'account_id' => $account->id,
                'store_id' => $store->id,
                'assets' => $assetsAdded,
                'wallet_design_version' => $program->wallet_design_version ?? 1,
            ]);

            return $pass->create();
        } finally {
            foreach (glob($tempDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }
    }

    /**
     * Convert hex color to RGB format for Apple Wallet
     */
    protected function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
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
     * @param  int  $stampCount  Current stamp count
     * @param  int  $rewardTarget  Target stamps needed
     * @return string Circle indicators string
     */
    protected function generateCircleIndicators(int $stampCount, int $rewardTarget): string
    {
        // Clamp stamp count to valid range (0 to reward_target)
        $filled = max(0, min($stampCount, $rewardTarget));
        $empty = $rewardTarget - $filled;

        // Unicode circles: filled = ● (U+25CF), empty = ○ (U+25CB)
        return str_repeat('●', $filled).str_repeat('○', $empty);
    }

    /**
     * Format token for manual entry (adds dashes for readability).
     * Works for any length; 16-char example: "abcd1234efgh5678" -> "abcd-1234-efgh-5678".
     *
     * @param  string  $token  The token to format
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
        if (! function_exists('imagecreatetruecolor')) {
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
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
