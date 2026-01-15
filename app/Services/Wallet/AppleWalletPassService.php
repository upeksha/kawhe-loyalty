<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use Byte5\LaravelPassGenerator\PassGenerator;
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

        // Build pass definition
        $passDefinition = [
            'formatVersion' => 1,
            'passTypeIdentifier' => config('passgenerator.pass_type_identifier'),
            'teamIdentifier' => config('passgenerator.team_identifier'),
            'organizationName' => config('passgenerator.organization_name'),
            'description' => 'Kawhe Loyalty Card',
            'serialNumber' => $this->generateSerialNumber($account),
            'logoText' => $store->name,
            'barcode' => [
                'message' => 'LA:' . $account->public_token, // Critical: must match web QR format for scanner compatibility
                'format' => 'PKBarcodeFormatQR',
                'messageEncoding' => 'utf-8',
            ],
            'storeCard' => [
                'primaryFields' => [
                    [
                        'key' => 'stamps',
                        'label' => 'Stamps',
                        'value' => sprintf('%d/%d', $account->stamp_count, $store->reward_target ?? 10),
                    ],
                ],
                'secondaryFields' => [
                    [
                        'key' => 'rewards',
                        'label' => 'Rewards',
                        'value' => (string) ($account->reward_balance ?? 0),
                    ],
                ],
                'auxiliaryFields' => [
                    [
                        'key' => 'customer',
                        'label' => 'Customer',
                        'value' => $customer->name ?? $customer->email ?? 'Valued Customer',
                    ],
                ],
                'backFields' => [
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

        // Add colors if store has branding
        if ($store->brand_color) {
            $passDefinition['backgroundColor'] = $this->hexToRgb($store->background_color ?? '#1F2937');
            $passDefinition['foregroundColor'] = $this->hexToRgb($store->brand_color ?? '#FFFFFF');
            $passDefinition['labelColor'] = $this->hexToRgb($store->brand_color ?? '#FFFFFF');
        }

        // Initialize pass generator with certificate configuration
        // Note: Verify PassGenerator API matches byte5/laravel-passgenerator package
        // Methods may need adjustment based on actual package implementation
        $passGenerator = new PassGenerator();
        
        // Configure certificates
        $certificatePath = storage_path('app/' . config('passgenerator.certificate_path'));
        $wwdrPath = storage_path('app/' . config('passgenerator.wwdr_certificate'));
        
        if (file_exists($certificatePath)) {
            // Set certificate - verify method name matches package API
            if (method_exists($passGenerator, 'setCertificate')) {
                $passGenerator->setCertificate($certificatePath, config('passgenerator.certificate_pass'));
            } elseif (method_exists($passGenerator, 'certificate')) {
                $passGenerator->certificate($certificatePath, config('passgenerator.certificate_pass'));
            }
        }
        if (file_exists($wwdrPath)) {
            // Set WWDR certificate - verify method name matches package API
            if (method_exists($passGenerator, 'setWWDRCertificate')) {
                $passGenerator->setWWDRCertificate($wwdrPath);
            } elseif (method_exists($passGenerator, 'wwdrCertificate')) {
                $passGenerator->wwdrCertificate($wwdrPath);
            }
        }
        
        // Set pass definition - verify method name matches package API
        if (method_exists($passGenerator, 'setPassDefinition')) {
            $passGenerator->setPassDefinition($passDefinition);
        } elseif (method_exists($passGenerator, 'passDefinition')) {
            $passGenerator->passDefinition($passDefinition);
        } elseif (method_exists($passGenerator, 'setData')) {
            $passGenerator->setData($passDefinition);
        }

        // Add assets (images) - verify method name matches package API
        $assetsPath = resource_path('wallet/apple/default');
        $addAssetMethod = null;
        if (method_exists($passGenerator, 'addAsset')) {
            $addAssetMethod = 'addAsset';
        } elseif (method_exists($passGenerator, 'asset')) {
            $addAssetMethod = 'asset';
        } elseif (method_exists($passGenerator, 'addImage')) {
            $addAssetMethod = 'addImage';
        }
        
        if ($addAssetMethod) {
            if (file_exists($assetsPath . '/icon.png')) {
                $passGenerator->$addAssetMethod('icon.png', file_get_contents($assetsPath . '/icon.png'));
            }
            if (file_exists($assetsPath . '/logo.png')) {
                $passGenerator->$addAssetMethod('logo.png', file_get_contents($assetsPath . '/logo.png'));
            }
            if (file_exists($assetsPath . '/background.png')) {
                $passGenerator->$addAssetMethod('background.png', file_get_contents($assetsPath . '/background.png'));
            }
            if (file_exists($assetsPath . '/strip.png')) {
                $passGenerator->$addAssetMethod('strip.png', file_get_contents($assetsPath . '/strip.png'));
            }
        }

        // Generate and return pkpass binary - verify method name matches package API
        if (method_exists($passGenerator, 'generate')) {
            return $passGenerator->generate();
        } elseif (method_exists($passGenerator, 'create')) {
            return $passGenerator->create();
        } elseif (method_exists($passGenerator, 'output')) {
            return $passGenerator->output();
        } else {
            throw new \Exception('PassGenerator does not have a generate/create/output method. Please verify package API.');
        }
    }

    /**
     * Generate stable unique serial number for pass
     *
     * @param LoyaltyAccount $account
     * @return string
     */
    protected function generateSerialNumber(LoyaltyAccount $account): string
    {
        // Use a stable format: kawhe-{store_id}-{customer_id}
        // This ensures the same account always gets the same serial number
        return sprintf('kawhe-%d-%d', $account->store_id, $account->customer_id);
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
}
