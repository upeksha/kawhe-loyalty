<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use Google_Client;
use Google_Service_Walletobjects;
use Google_Service_Walletobjects_LoyaltyClass;
use Google_Service_Walletobjects_LoyaltyObject;
use Illuminate\Support\Facades\Log;

class GoogleWalletPassService
{
    protected $client;
    protected $service;
    protected $issuerId;
    protected $classId;

    public function __construct()
    {
        $this->issuerId = config('services.google_wallet.issuer_id');
        $this->classId = config('services.google_wallet.class_id', 'loyalty_class_kawhe');
        
        // Initialize Google Client
        $this->client = new Google_Client();
        $this->client->setApplicationName('Kawhe Loyalty');
        // Use full scope URL for Wallet API
        $this->client->setScopes('https://www.googleapis.com/auth/wallet_object.issuer');
        
        // Load service account credentials
        $serviceAccountPath = config('services.google_wallet.service_account_key');
        if (!$serviceAccountPath) {
            throw new \Exception('Google Wallet service account key path not configured. Please set GOOGLE_WALLET_SERVICE_ACCOUNT_KEY in .env');
        }
        
        // Resolve path (handle both relative and absolute paths)
        if (!file_exists($serviceAccountPath)) {
            // Try relative to storage/app/private
            $relativePath = storage_path('app/private/' . $serviceAccountPath);
            if (file_exists($relativePath)) {
                $serviceAccountPath = $relativePath;
            } else {
                // Try absolute path from project root
                $absolutePath = base_path($serviceAccountPath);
                if (file_exists($absolutePath)) {
                    $serviceAccountPath = $absolutePath;
                } else {
                    throw new \Exception('Google Wallet service account key not found at: ' . $serviceAccountPath . '. Also tried: ' . $relativePath . ' and ' . $absolutePath);
                }
            }
        }
        
        if (!is_readable($serviceAccountPath)) {
            throw new \Exception('Google Wallet service account key file is not readable. Check file permissions.');
        }
        
        $this->client->setAuthConfig($serviceAccountPath);
        
        $this->service = new Google_Service_Walletobjects($this->client);
    }

    /**
     * Get the Google Wallet service instance
     *
     * @return Google_Service_Walletobjects
     */
    public function getService(): Google_Service_Walletobjects
    {
        return $this->service;
    }

    /**
     * Create or get loyalty class (template for passes)
     * This should be called once during setup
     *
     * @param \App\Models\Store $store
     * @return Google_Service_Walletobjects_LoyaltyClass
     */
    public function createLoyaltyClass($store)
    {
        $classId = $this->getClassIdForStore($store);
        
        try {
            // Try to get existing class
            return $this->service->loyaltyclass->get("{$this->issuerId}.{$classId}");
        } catch (\Exception $e) {
            // Class doesn't exist, create it
            $loyaltyClass = new Google_Service_Walletobjects_LoyaltyClass();
            $loyaltyClass->setId("{$this->issuerId}.{$classId}");
            $loyaltyClass->setIssuerName(config('app.name', 'Kawhe'));
            $loyaltyClass->setProgramName($store->name);
            
            // Google Wallet requires a program logo - use store logo or default
            $logoUri = $this->getLogoUri($store);
            if (!$logoUri) {
                // Use default logo if store doesn't have one
                $logoUri = $this->getDefaultLogoUri();
            }
            $loyaltyClass->setProgramLogo($logoUri);
            
            // Set review status based on environment
            // For production, this should be 'APPROVED' after Google reviews your account
            // For testing, use 'UNDER_REVIEW' and add test users in Google Wallet Console
            $reviewStatus = config('services.google_wallet.review_status', 'UNDER_REVIEW');
            $loyaltyClass->setReviewStatus($reviewStatus);
            
            // Add text modules
            $textModulesData = [
                [
                    'header' => 'Reward Target',
                    'body' => "Collect {$store->reward_target} stamps to earn: {$store->reward_title}",
                ],
            ];
            $loyaltyClass->setTextModulesData($textModulesData);
            
            // Add image modules (if store has logo)
            $logoImage = $this->getLogoUri($store);
            if ($logoImage) {
                $imageModulesData = [
                    [
                        'mainImage' => $logoImage,
                    ],
                ];
                $loyaltyClass->setImageModulesData($imageModulesData);
            }
            
            // Note: Barcode is set on LoyaltyObject, not LoyaltyClass
            // The class is just a template, barcodes are per-object
            
            return $this->service->loyaltyclass->insert($loyaltyClass);
        }
    }

    /**
     * Create or update loyalty object (individual pass for customer)
     *
     * @param LoyaltyAccount $account
     * @return Google_Service_Walletobjects_LoyaltyObject
     */
    public function createOrUpdateLoyaltyObject(LoyaltyAccount $account)
    {
        $account->load(['store', 'customer']);
        $store = $account->store;
        $customer = $account->customer;
        
        $objectId = $this->getObjectIdForAccount($account);
        
        // Ensure class exists
        $this->createLoyaltyClass($store);
        
        $loyaltyObject = new Google_Service_Walletobjects_LoyaltyObject();
        $loyaltyObject->setId($objectId);
        $loyaltyObject->setClassId("{$this->issuerId}.{$this->getClassIdForStore($store)}");
        $loyaltyObject->setState('ACTIVE');
        
        // Account info - accountName must be a plain string, not LocalizedString
        $accountNameValue = $customer->name ?? $customer->email ?? 'Valued Customer';
        $loyaltyObject->setAccountName($accountNameValue);
        
        // Account ID (stable identifier)
        $loyaltyObject->setAccountId((string) $account->id);
        
        // Loyalty points (stamp count)
        $loyaltyPoints = new \Google_Service_Walletobjects_LoyaltyPoints();
        $loyaltyPoints->setLabel('Stamps');
        $loyaltyPoints->setBalance(new \Google_Service_Walletobjects_LoyaltyPointsBalance([
            'int' => $account->stamp_count,
        ]));
        $loyaltyObject->setLoyaltyPoints($loyaltyPoints);
        
        // Secondary points (rewards)
        // Google API requires a LoyaltyPoints object (cannot pass null). Use 0 when no rewards.
        $secondaryPoints = new \Google_Service_Walletobjects_LoyaltyPoints();
        $secondaryPoints->setLabel('Rewards');
        $secondaryPoints->setBalance(new \Google_Service_Walletobjects_LoyaltyPointsBalance([
            'int' => max(0, $account->reward_balance ?? 0),
        ]));
        $loyaltyObject->setSecondaryLoyaltyPoints($secondaryPoints);
        
        // Dynamic barcode: LR:{redeem_token} when reward available, else LA:{public_token}
        // This matches Apple Wallet behavior for consistency
        $barcodeValue = ($account->reward_balance ?? 0) > 0 && $account->redeem_token
            ? 'LR:' . $account->redeem_token
            : 'LA:' . $account->public_token;
        
        $barcode = new \Google_Service_Walletobjects_Barcode();
        $barcode->setType('QR_CODE');
        $barcode->setValue($barcodeValue);
        $barcode->setAlternateText(
            ($account->reward_balance ?? 0) > 0 && $account->redeem_token
                ? 'Scan to redeem'
                : 'Scan to stamp'
        );
        $loyaltyObject->setBarcode($barcode);
        
        // Text modules
        $textModulesData = [
            [
                'header' => 'Current Status',
                'body' => sprintf('%d / %d stamps', $account->stamp_count, $store->reward_target ?? 10),
            ],
        ];
        
        if (($account->reward_balance ?? 0) > 0) {
            $textModulesData[] = [
                'header' => 'Available Rewards',
                'body' => (string) $account->reward_balance . ' ' . $store->reward_title,
            ];
        }
        
        $loyaltyObject->setTextModulesData($textModulesData);
        
        try {
            // First, try to get existing object to check if it exists
            try {
                $existing = $this->service->loyaltyobject->get($objectId);
                
                // Check if there's a significant change that warrants a notification
                $previousStampCount = $existing->getLoyaltyPoints()?->getBalance()?->getInt() ?? 0;
                $previousRewardBalance = $existing->getSecondaryLoyaltyPoints()?->getBalance()?->getInt() ?? 0;
                
                $stampChanged = $previousStampCount !== $account->stamp_count;
                $rewardChanged = $previousRewardBalance !== ($account->reward_balance ?? 0);
                $rewardEarned = ($account->reward_balance ?? 0) > $previousRewardBalance;
                $rewardRedeemed = ($account->reward_balance ?? 0) < $previousRewardBalance;
                
                // Add notification message for significant changes
                // Note: Google limits to 3 notifications per 24 hours per pass
                if ($stampChanged || $rewardChanged) {
                    $messages = [];
                    
                    if ($rewardEarned) {
                        // Reward earned - most important notification
                        $messages[] = [
                            'header' => '🎉 Reward Earned!',
                            'body' => sprintf('You earned %d %s!', ($account->reward_balance ?? 0) - $previousRewardBalance, $store->reward_title ?? 'reward'),
                            'actionUri' => [
                                'uri' => config('app.url') . '/c/' . $account->public_token,
                                'description' => 'View Card',
                            ],
                        ];
                    } elseif ($rewardRedeemed) {
                        // Reward redeemed
                        $redeemedCount = $previousRewardBalance - ($account->reward_balance ?? 0);
                        $remainingCount = $account->reward_balance ?? 0;
                        if ($remainingCount > 0) {
                            $messages[] = [
                                'header' => '✅ Reward Redeemed!',
                                'body' => sprintf('You redeemed %d reward(s). %d remaining.', $redeemedCount, $remainingCount),
                            ];
                        } else {
                            $messages[] = [
                                'header' => '✅ Reward Redeemed!',
                                'body' => sprintf('You redeemed %d reward(s). Keep earning stamps for more rewards!', $redeemedCount),
                            ];
                        }
                    } elseif ($stampChanged) {
                        // Stamp count changed
                        $stampDiff = $account->stamp_count - $previousStampCount;
                        if ($stampDiff > 0) {
                            $messages[] = [
                                'header' => '✅ Stamped!',
                                'body' => sprintf('You now have %d / %d stamps', $account->stamp_count, $store->reward_target ?? 10),
                            ];
                        }
                    }
                    
                    if (!empty($messages)) {
                        $loyaltyObject->setMessages($messages);
                        Log::info('Google Wallet: Adding notification message', [
                            'object_id' => $objectId,
                            'message_count' => count($messages),
                        ]);
                    }
                }
                
                // Object exists - use patch for partial update (more efficient and safer)
                // Patch only updates the fields we send, preserving other fields
                Log::info('Google Wallet: Patching existing loyalty object', [
                    'object_id' => $objectId,
                    'stamp_count' => $account->stamp_count,
                    'reward_balance' => $account->reward_balance ?? 0,
                    'stamp_changed' => $stampChanged,
                    'reward_changed' => $rewardChanged,
                ]);
                
                return $this->service->loyaltyobject->patch($objectId, $loyaltyObject);
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() === 404) {
                    // Object doesn't exist, create it
                    Log::info('Google Wallet: Creating new loyalty object', [
                        'object_id' => $objectId,
                        'stamp_count' => $account->stamp_count,
                        'reward_balance' => $account->reward_balance ?? 0,
                    ]);
                    return $this->service->loyaltyobject->insert($loyaltyObject);
                }
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Google Wallet: Failed to create/update loyalty object', [
                'object_id' => $objectId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate "Save to Google Wallet" JWT link
     *
     * @param LoyaltyAccount $account
     * @return string Full URL for "Save to Google Wallet"
     */
    public function generateSaveLink(LoyaltyAccount $account): string
    {
        // First ensure the object exists
        try {
            $this->createOrUpdateLoyaltyObject($account);
        } catch (\Exception $e) {
            Log::error('Failed to create/update Google Wallet object', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
        
        $objectId = $this->getObjectIdForAccount($account);
        $classId = "{$this->issuerId}.{$this->getClassIdForStore($account->store)}";
        
        // Get service account email from credentials
        $serviceAccountPath = config('services.google_wallet.service_account_key');
        
        // Resolve path (same logic as constructor)
        if (!file_exists($serviceAccountPath)) {
            $relativePath = storage_path('app/private/' . $serviceAccountPath);
            if (file_exists($relativePath)) {
                $serviceAccountPath = $relativePath;
            } else {
                $absolutePath = base_path($serviceAccountPath);
                if (file_exists($absolutePath)) {
                    $serviceAccountPath = $absolutePath;
                } else {
                    throw new \Exception('Service account key file not found. Tried: ' . config('services.google_wallet.service_account_key') . ', ' . $relativePath . ', ' . $absolutePath);
                }
            }
        }
        
        if (!is_readable($serviceAccountPath)) {
            throw new \Exception('Service account key file is not readable: ' . $serviceAccountPath);
        }
        
        $credentials = json_decode(file_get_contents($serviceAccountPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON in service account key file: ' . json_last_error_msg());
        }
        
        $serviceAccountEmail = $credentials['client_email'] ?? null;
        
        if (!$serviceAccountEmail) {
            throw new \Exception('Service account email not found in credentials');
        }
        
        // Create JWT payload for Google Wallet
        $now = time();
        $payload = [
            'iss' => $serviceAccountEmail,
            'aud' => 'google',
            'origins' => [config('app.url')],
            'typ' => 'savetowallet',
            'iat' => $now,
            'payload' => [
                'loyaltyObjects' => [
                    [
                        'id' => $objectId,
                    ],
                ],
            ],
        ];
        
        // Sign JWT using service account private key
        $jwt = $this->signJwt($payload, $credentials['private_key']);
        
        // Return full Google Wallet save URL
        return 'https://pay.google.com/gp/v/save/' . $jwt;
    }

    /**
     * Sign JWT using service account private key
     *
     * @param array $payload
     * @param string $privateKey
     * @return string
     */
    protected function signJwt(array $payload, string $privateKey): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];
        
        $segments = [];
        $segments[] = $this->base64UrlEncode(json_encode($header));
        $segments[] = $this->base64UrlEncode(json_encode($payload));
        
        $signingInput = implode('.', $segments);
        
        // Load private key resource
        $keyResource = openssl_pkey_get_private($privateKey);
        if (!$keyResource) {
            throw new \Exception('Failed to load private key: ' . openssl_error_string());
        }
        
        $signature = '';
        if (!openssl_sign($signingInput, $signature, $keyResource, OPENSSL_ALGO_SHA256)) {
            // Key resource is automatically freed in PHP 8.0+
            throw new \Exception('Failed to sign JWT: ' . openssl_error_string());
        }
        
        // Key resource is automatically freed in PHP 8.0+ (no need for openssl_free_key)
        
        $segments[] = $this->base64UrlEncode($signature);
        
        return implode('.', $segments);
    }

    /**
     * Base64 URL encode (without padding)
     *
     * @param string $data
     * @return string
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get stable class ID for a store
     *
     * @param \App\Models\Store $store
     * @return string
     */
    protected function getClassIdForStore($store): string
    {
        return sprintf('loyalty_class_%d', $store->id);
    }

    /**
     * Get stable object ID for a loyalty account
     *
     * @param LoyaltyAccount $account
     * @return string
     */
    protected function getObjectIdForAccount(LoyaltyAccount $account): string
    {
        return sprintf('%s.%s', $this->issuerId, sprintf('loyalty_object_%d', $account->id));
    }

    /**
     * Get logo Image object for store
     *
     * @param \App\Models\Store $store
     * @return \Google_Service_Walletobjects_Image|null
     */
    protected function getLogoUri($store)
    {
        if (!$store->logo_path) {
            return null;
        }
        
        // Google Wallet requires absolute HTTPS URL
        $appUrl = rtrim(config('app.url'), '/');
        $logoUrl = $appUrl . '/storage/' . $store->logo_path;
        
        // Create Image object (not ImageUri)
        $image = new \Google_Service_Walletobjects_Image();
        $imageUri = new \Google_Service_Walletobjects_ImageUri();
        $imageUri->setUri($logoUrl);
        $image->setSourceUri($imageUri);
        
        return $image;
    }

    /**
     * Get default logo Image object (required by Google Wallet)
     *
     * @return \Google_Service_Walletobjects_Image
     */
    protected function getDefaultLogoUri()
    {
        // Use default logo - Google Wallet requires absolute HTTPS URL
        $defaultLogoPath = 'wallet/google/program-logo.png';
        
        // Ensure we use absolute URL (Google Wallet requirement)
        $appUrl = rtrim(config('app.url'), '/');
        $defaultLogoUrl = $appUrl . '/' . $defaultLogoPath;
        
        // Verify file exists, if not log warning but still use the URL
        if (!file_exists(public_path($defaultLogoPath))) {
            \Log::warning('Google Wallet: Default logo file not found', [
                'path' => public_path($defaultLogoPath),
                'url' => $defaultLogoUrl,
            ]);
        }
        
        $image = new \Google_Service_Walletobjects_Image();
        $imageUri = new \Google_Service_Walletobjects_ImageUri();
        $imageUri->setUri($defaultLogoUrl);
        $image->setSourceUri($imageUri);
        
        return $image;
    }
}
