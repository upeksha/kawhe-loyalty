<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use Google_Client;
use Google_Service_Walletobjects;
use Google_Service_Walletobjects_LoyaltyClass;
use Google_Service_Walletobjects_LoyaltyObject;
use Illuminate\Support\Facades\Cache;
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
        $resourceId = "{$this->issuerId}.{$classId}";

        // Build logo, hero/logo image, and color (always use fallback so pass shows color and image)
        $logoUri = $this->getPassLogoUri($store);
        if (!$logoUri) {
            $logoUri = $this->getLogoUri($store);
        }
        if (!$logoUri) {
            $logoUri = $this->getDefaultLogoUri();
        }

        $heroImage = $this->getPassHeroImageUri($store);
        if (!$heroImage) {
            $heroImage = $this->getPassLogoUri($store);
        }
        if (!$heroImage) {
            $heroImage = $this->getLogoUri($store);
        }
        if (!$heroImage) {
            $heroImage = $this->getDefaultLogoUri();
        }
        $imageModulesData = $this->buildImageModulesData($heroImage);

        // Always set background color (Google requires it for pass to show color)
        $backgroundColor = $store->background_color ?? '#1F2937';
        // Ensure hex format for Google
        $backgroundColor = ltrim($backgroundColor, '#');
        if (strlen($backgroundColor) === 3) {
            $backgroundColor = $backgroundColor[0].$backgroundColor[0].$backgroundColor[1].$backgroundColor[1].$backgroundColor[2].$backgroundColor[2];
        }
        $backgroundColor = '#' . $backgroundColor;

        try {
            // Class exists: patch to keep branding in sync
            $existing = $this->service->loyaltyclass->get($resourceId);
            $rewardTarget = $store->reward_target ?? 10;
            $reviewStatus = $this->normalizeReviewStatusForPatch();

            if (! $this->shouldPatchLoyaltyClass($store, $existing, $logoUri, $heroImage, $backgroundColor, $rewardTarget)) {
                Log::info('Google Wallet: Skipping loyalty class patch (no changes)', [
                    'store_id' => $store->id,
                    'class_id' => $resourceId,
                ]);
                return $existing;
            }

            Log::info('Google Wallet: Patching loyalty class', [
                'store_id' => $store->id,
                'class_id' => $resourceId,
                'review_status' => $reviewStatus,
            ]);

            // Patch core fields first (name/color/text) so store name is always correct.
            $basePatch = new \Google_Service_Walletobjects_LoyaltyClass();
            $basePatch->setId($resourceId);
            $basePatch->setIssuerName(config('app.name', 'Kawhe'));
            $basePatch->setProgramName($store->name);
            $basePatch->setTextModulesData([
                ['header' => 'Reward Target', 'body' => "Collect {$rewardTarget} stamps to earn: " . ($store->reward_title ?? 'rewards')],
            ]);
            $basePatch->setHexBackgroundColor($backgroundColor);
            $basePatch->setReviewStatus($reviewStatus);

            try {
                $this->service->loyaltyclass->patch($resourceId, $basePatch);
            } catch (\Throwable $basePatchError) {
                Log::warning('Google Wallet: Failed to patch base loyalty class fields', [
                    'class_id' => $resourceId,
                    'error' => $basePatchError->getMessage(),
                ]);
            }

            // Patch image branding separately so image issues don't block name/color updates.
            $imagePatch = new \Google_Service_Walletobjects_LoyaltyClass();
            $imagePatch->setId($resourceId);
            $imagePatch->setProgramLogo($logoUri);
            $imagePatch->setImageModulesData($imageModulesData);
            $imagePatch->setReviewStatus($reviewStatus);
            try {
                $result = $this->service->loyaltyclass->patch($resourceId, $imagePatch);
                $this->cacheClassSync($store);
                return $result;
            } catch (\Throwable $imagePatchError) {
                Log::warning('Google Wallet: Failed to patch loyalty class images, using existing', [
                    'class_id' => $resourceId,
                    'error' => $imagePatchError->getMessage(),
                ]);
                return $existing;
            }
        } catch (\Exception $e) {
            // Class doesn't exist: create it with image and color
            $loyaltyClass = new \Google_Service_Walletobjects_LoyaltyClass();
            $loyaltyClass->setId($resourceId);
            $loyaltyClass->setIssuerName(config('app.name', 'Kawhe'));
            $loyaltyClass->setProgramName($store->name);
            $loyaltyClass->setProgramLogo($logoUri);
            $loyaltyClass->setReviewStatus($this->normalizeReviewStatusForCreate());
            $rewardTarget = $store->reward_target ?? 10;
            $loyaltyClass->setTextModulesData([
                ['header' => 'Reward Target', 'body' => "Collect {$rewardTarget} stamps to earn: " . ($store->reward_title ?? 'rewards')],
            ]);
            $loyaltyClass->setImageModulesData($imageModulesData);
            $loyaltyClass->setHexBackgroundColor($backgroundColor);
            $result = $this->service->loyaltyclass->insert($loyaltyClass);
            $this->cacheClassSync($store);
            return $result;
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

        // Ensure 4-char manual entry code exists (e.g. for accounts created before migration or pass never updated)
        if (empty($account->manual_entry_code) && $account->store_id) {
            $account->manual_entry_code = LoyaltyAccount::generateManualEntryCode($account->store_id);
            $account->saveQuietly();
        }
        
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
        
        // Loyalty points (stamp count) – label so Google Wallet shows "Stamps" clearly
        $loyaltyPoints = new \Google_Service_Walletobjects_LoyaltyPoints();
        $loyaltyPoints->setLabel('Stamps');
        $loyaltyPoints->setBalance(new \Google_Service_Walletobjects_LoyaltyPointsBalance([
            'int' => $account->stamp_count,
        ]));
        $loyaltyObject->setLoyaltyPoints($loyaltyPoints);
        
        // Secondary points (rewards) – must show actual reward balance so the card displays it
        $rewardBalance = (int) ($account->reward_balance ?? 0);
        $secondaryPoints = new \Google_Service_Walletobjects_LoyaltyPoints();
        $secondaryPoints->setLabel('Rewards');
        $secondaryPoints->setBalance(new \Google_Service_Walletobjects_LoyaltyPointsBalance([
            'int' => $rewardBalance,
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
        // Show the manual code directly under the QR code
        $barcode->setAlternateText('Manual Code: ' . ($account->manual_entry_code ?? $this->formatTokenForManualEntry(
            ($account->reward_balance ?? 0) > 0 && $account->redeem_token
                ? $account->redeem_token
                : $account->public_token
        )));
        $loyaltyObject->setBarcode($barcode);
        
        // Hero image on the card (Pass Hero Image) – same as class so card shows it
        $heroImage = $this->getPassHeroImageUri($store);
        if (!$heroImage) {
            $heroImage = $this->getPassLogoUri($store);
        }
        if (!$heroImage) {
            $heroImage = $this->getLogoUri($store);
        }
        if (!$heroImage) {
            $heroImage = $this->getDefaultLogoUri();
        }
        $objectImageModules = $this->buildImageModulesData($heroImage);
        if (!empty($objectImageModules)) {
            $loyaltyObject->setImageModulesData($objectImageModules);
        }
        
        // Note: Background color is set on LoyaltyClass, not LoyaltyObject
        // The object inherits styling from the class
        
        // Text modules: stamp progress circles first (like Apple Wallet), then customer, then rewards
        $rewardTarget = $store->reward_target ?? 10;
        $circleIndicators = $this->generateCircleIndicators($account->stamp_count, $rewardTarget);
        $textModulesData = [
            [
                'header' => 'Progress',
                'body' => $circleIndicators . '  ' . sprintf('%d / %d stamps', $account->stamp_count, $rewardTarget),
            ],
            [
                'header' => 'Customer',
                'body' => $customer->name ?? $customer->email ?? 'Valued Customer',
            ],
        ];
        if (($account->reward_balance ?? 0) > 0) {
            $textModulesData[] = [
                'header' => 'Rewards',
                'body' => '🎁 ' . (string) ($account->reward_balance ?? 0) . ' available to redeem',
            ];
        }
        $loyaltyObject->setTextModulesData($textModulesData);

        Log::info('Google Wallet: Preparing loyalty object', [
            'store_id' => $store->id,
            'customer_id' => $customer?->id,
            'account_id' => $account->id,
            'class_id' => "{$this->issuerId}.{$this->getClassIdForStore($store)}",
            'object_id' => $objectId,
            'account_name' => $accountNameValue,
            'public_token' => $account->public_token,
        ]);
        
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
     * Normalize reviewStatus for patch requests.
     */
    protected function normalizeReviewStatusForPatch(): string
    {
        $raw = config('services.google_wallet.review_status', 'UNDER_REVIEW');
        $normalized = $this->sanitizeReviewStatus($raw);

        return $normalized === 'DRAFT' ? 'DRAFT' : 'UNDER_REVIEW';
    }

    /**
     * Normalize reviewStatus for create requests.
     */
    protected function normalizeReviewStatusForCreate(): string
    {
        $raw = config('services.google_wallet.review_status', 'UNDER_REVIEW');
        $normalized = $this->sanitizeReviewStatus($raw);
        $allowed = ['UNDER_REVIEW', 'APPROVED', 'DRAFT'];

        return in_array($normalized, $allowed, true) ? $normalized : 'UNDER_REVIEW';
    }

    /**
     * Remove Optional[...] wrapper and normalize casing.
     */
    protected function sanitizeReviewStatus(?string $value): string
    {
        if (! $value) {
            return '';
        }

        $value = strtoupper(trim($value));
        if (preg_match('/OPTIONAL\[(.*?)\]/', $value, $matches)) {
            return strtoupper($matches[1]);
        }

        return $value;
    }

    /**
     * Build image modules using proper API objects.
     *
     * @param \Google_Service_Walletobjects_Image|null $heroImage
     * @return array
     */
    protected function buildImageModulesData($heroImage): array
    {
        if (! $heroImage) {
            return [];
        }

        $imageModule = new \Google_Service_Walletobjects_ImageModuleData();
        $imageModule->setMainImage($heroImage);
        return [$imageModule];
    }

    /**
     * Determine if we should patch the loyalty class.
     */
    protected function shouldPatchLoyaltyClass($store, $existing, $logoUri, $heroImage, string $backgroundColor, int $rewardTarget): bool
    {
        $cacheKey = $this->classSyncCacheKey($store->id);
        $lastSync = Cache::get($cacheKey);
        if ($lastSync && $store->updated_at && $store->updated_at->timestamp <= $lastSync) {
            if (! $this->existingClassDiffers($existing, $store, $logoUri, $heroImage, $backgroundColor, $rewardTarget)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare existing class fields to desired values.
     */
    protected function existingClassDiffers($existing, $store, $logoUri, $heroImage, string $backgroundColor, int $rewardTarget): bool
    {
        $desiredProgramName = $store->name;
        $desiredTextHeader = 'Reward Target';
        $desiredTextBody = "Collect {$rewardTarget} stamps to earn: " . ($store->reward_title ?? 'rewards');

        $existingProgramName = $existing->getProgramName();
        $existingColor = $existing->getHexBackgroundColor();
        $textModules = $existing->getTextModulesData() ?? [];
        $existingTextHeader = $textModules[0]?->getHeader();
        $existingTextBody = $textModules[0]?->getBody();

        $existingLogoUri = $this->extractImageUri($existing->getProgramLogo());
        $existingHeroUri = null;
        $existingImageModules = $existing->getImageModulesData() ?? [];
        if (!empty($existingImageModules)) {
            $existingHeroUri = $this->extractImageUri($existingImageModules[0]?->getMainImage());
        }

        $desiredLogoUri = $this->extractImageUri($logoUri);
        $desiredHeroUri = $this->extractImageUri($heroImage);

        return $existingProgramName !== $desiredProgramName
            || $existingColor !== $backgroundColor
            || $existingTextHeader !== $desiredTextHeader
            || $existingTextBody !== $desiredTextBody
            || $existingLogoUri !== $desiredLogoUri
            || $existingHeroUri !== $desiredHeroUri;
    }

    /**
     * Cache successful class sync time.
     */
    protected function cacheClassSync($store): void
    {
        Cache::put($this->classSyncCacheKey($store->id), time(), 60 * 60 * 24);
    }

    protected function classSyncCacheKey(int $storeId): string
    {
        return "google_wallet_class_sync_at:{$storeId}";
    }

    /**
     * Extract URL from a Google Wallet Image object.
     */
    protected function extractImageUri($image): ?string
    {
        if (! $image) {
            return null;
        }

        $source = $image->getSourceUri();
        return $source ? $source->getUri() : null;
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
    /**
     * Ensure URL is HTTPS (Google Wallet requires HTTPS for images).
     */
    protected function ensureHttps(string $url): string
    {
        return str_starts_with($url, 'http://') ? 'https://' . substr($url, 7) : $url;
    }

    protected function getLogoUri($store)
    {
        if (!$store->logo_path) {
            return null;
        }
        
        $appUrl = rtrim(config('app.url'), '/');
        $logoUrl = $this->ensureHttps($appUrl . '/storage/' . $store->logo_path);
        
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
        $defaultLogoPath = 'wallet/google/program-logo.png';
        $appUrl = rtrim(config('app.url'), '/');
        $defaultLogoUrl = $this->ensureHttps($appUrl . '/' . $defaultLogoPath);
        
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

    /**
     * Get pass logo Image object for store (for wallet passes)
     *
     * @param \App\Models\Store $store
     * @return \Google_Service_Walletobjects_Image|null
     */
    protected function getPassLogoUri($store)
    {
        if (!$store->pass_logo_path) {
            return null;
        }
        
        $appUrl = rtrim(config('app.url'), '/');
        $logoUrl = $this->ensureHttps($appUrl . '/storage/' . $store->pass_logo_path);
        
        $image = new \Google_Service_Walletobjects_Image();
        $imageUri = new \Google_Service_Walletobjects_ImageUri();
        $imageUri->setUri($logoUrl);
        $image->setSourceUri($imageUri);
        
        return $image;
    }

    /**
     * Get pass hero image Image object for store (for wallet passes)
     *
     * @param \App\Models\Store $store
     * @return \Google_Service_Walletobjects_Image|null
     */
    protected function getPassHeroImageUri($store)
    {
        if (!$store->pass_hero_image_path) {
            return null;
        }
        
        $appUrl = rtrim(config('app.url'), '/');
        $heroUrl = $this->ensureHttps($appUrl . '/storage/' . $store->pass_hero_image_path);
        
        $image = new \Google_Service_Walletobjects_Image();
        $imageUri = new \Google_Service_Walletobjects_ImageUri();
        $imageUri->setUri($heroUrl);
        $image->setSourceUri($imageUri);
        
        return $image;
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
