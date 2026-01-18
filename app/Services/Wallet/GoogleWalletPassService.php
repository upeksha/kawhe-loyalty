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
        $this->client->setScopes(Google_Service_Walletobjects::WALLET_OBJECT_ISSUER);
        
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
            $loyaltyClass->setProgramLogo($this->getLogoUri($store));
            $loyaltyClass->setReviewStatus('UNDER_REVIEW'); // Or 'APPROVED' if pre-approved
            
            // Add text modules
            $textModulesData = [
                [
                    'header' => 'Reward Target',
                    'body' => "Collect {$store->reward_target} stamps to earn: {$store->reward_title}",
                ],
            ];
            $loyaltyClass->setTextModulesData($textModulesData);
            
            // Add image modules (if store has logo)
            if ($store->logo_path) {
                $imageModulesData = [
                    [
                        'mainImage' => [
                            'sourceUri' => [
                                'uri' => $this->getLogoUri($store),
                            ],
                            'contentDescription' => [
                                'defaultValue' => [
                                    'language' => 'en-US',
                                    'value' => $store->name . ' Logo',
                                ],
                            ],
                        ],
                    ],
                ];
                $loyaltyClass->setImageModulesData($imageModulesData);
            }
            
            // Add barcode
            $barcode = new \Google_Service_Walletobjects_Barcode();
            $barcode->setType('QR_CODE');
            $barcode->setAlternateText('Scan to stamp');
            $loyaltyClass->setBarcode($barcode);
            
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
        
        // Account info
        $accountName = new \Google_Service_Walletobjects_LocalizedString();
        $accountName->setDefaultValue([
            'language' => 'en-US',
            'value' => $customer->name ?? $customer->email ?? 'Valued Customer',
        ]);
        $loyaltyObject->setAccountName($accountName);
        
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
        if (($account->reward_balance ?? 0) > 0) {
            $secondaryPoints = new \Google_Service_Walletobjects_LoyaltyPoints();
            $secondaryPoints->setLabel('Rewards');
            $secondaryPoints->setBalance(new \Google_Service_Walletobjects_LoyaltyPointsBalance([
                'int' => $account->reward_balance,
            ]));
            $loyaltyObject->setSecondaryLoyaltyPoints($secondaryPoints);
        }
        
        // Barcode with public_token
        $barcode = new \Google_Service_Walletobjects_Barcode();
        $barcode->setType('QR_CODE');
        $barcode->setValue('LA:' . $account->public_token); // Match scanner format
        $barcode->setAlternateText('Scan to stamp');
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
            // Try to update existing object
            return $this->service->loyaltyobject->update($objectId, $loyaltyObject);
        } catch (\Exception $e) {
            // Object doesn't exist, create it
            return $this->service->loyaltyobject->insert($loyaltyObject);
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
                }
            }
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
            openssl_free_key($keyResource);
            throw new \Exception('Failed to sign JWT: ' . openssl_error_string());
        }
        
        openssl_free_key($keyResource);
        
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
     * Get logo URI for store
     *
     * @param \App\Models\Store $store
     * @return \Google_Service_Walletobjects_ImageUri|null
     */
    protected function getLogoUri($store)
    {
        if (!$store->logo_path) {
            return null;
        }
        
        $imageUri = new \Google_Service_Walletobjects_ImageUri();
        $imageUri->setUri(asset('storage/' . $store->logo_path));
        
        return $imageUri;
    }
}
