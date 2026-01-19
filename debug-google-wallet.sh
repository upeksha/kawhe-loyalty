#!/bin/bash
# Google Wallet Debugging Script
# Run this on your server to identify the exact issue

echo "=========================================="
echo "Google Wallet Debugging Script"
echo "=========================================="
echo ""

# Check 1: Environment Variables
echo "1. Checking Environment Variables..."
echo "-----------------------------------"
if grep -q "GOOGLE_WALLET_ISSUER_ID" .env; then
    ISSUER_ID=$(grep "GOOGLE_WALLET_ISSUER_ID" .env | cut -d '=' -f2)
    echo "✅ GOOGLE_WALLET_ISSUER_ID: $ISSUER_ID"
else
    echo "❌ GOOGLE_WALLET_ISSUER_ID: NOT SET"
fi

if grep -q "GOOGLE_WALLET_CLASS_ID" .env; then
    CLASS_ID=$(grep "GOOGLE_WALLET_CLASS_ID" .env | cut -d '=' -f2)
    echo "✅ GOOGLE_WALLET_CLASS_ID: $CLASS_ID"
else
    echo "❌ GOOGLE_WALLET_CLASS_ID: NOT SET"
fi

if grep -q "GOOGLE_WALLET_SERVICE_ACCOUNT_KEY" .env; then
    KEY_PATH=$(grep "GOOGLE_WALLET_SERVICE_ACCOUNT_KEY" .env | cut -d '=' -f2)
    echo "✅ GOOGLE_WALLET_SERVICE_ACCOUNT_KEY: $KEY_PATH"
else
    echo "❌ GOOGLE_WALLET_SERVICE_ACCOUNT_KEY: NOT SET"
    exit 1
fi

echo ""

# Check 2: Service Account File
echo "2. Checking Service Account File..."
echo "-----------------------------------"
if [ -f "$KEY_PATH" ]; then
    echo "✅ File exists at: $KEY_PATH"
    echo "   Size: $(du -h "$KEY_PATH" | cut -f1)"
    echo "   Permissions: $(stat -c "%a" "$KEY_PATH")"
elif [ -f "storage/app/private/$KEY_PATH" ]; then
    echo "✅ File exists at: storage/app/private/$KEY_PATH"
    FULL_PATH="storage/app/private/$KEY_PATH"
    echo "   Size: $(du -h "$FULL_PATH" | cut -f1)"
    echo "   Permissions: $(stat -c "%a" "$FULL_PATH")"
    KEY_PATH="$FULL_PATH"
elif [ -f "/var/www/kawhe/storage/app/private/$KEY_PATH" ]; then
    FULL_PATH="/var/www/kawhe/storage/app/private/$KEY_PATH"
    echo "✅ File exists at: $FULL_PATH"
    echo "   Size: $(du -h "$FULL_PATH" | cut -f1)"
    echo "   Permissions: $(stat -c "%a" "$FULL_PATH")"
    KEY_PATH="$FULL_PATH"
else
    echo "❌ File NOT FOUND at any of these locations:"
    echo "   - $KEY_PATH"
    echo "   - storage/app/private/$KEY_PATH"
    echo "   - /var/www/kawhe/storage/app/private/$KEY_PATH"
    echo ""
    echo "Searching for JSON files..."
    find . -name "*.json" -path "*/google-wallet/*" 2>/dev/null | head -5
    exit 1
fi

echo ""

# Check 3: JSON Validity
echo "3. Checking JSON Validity..."
echo "-----------------------------------"
if php -r "json_decode(file_get_contents('$KEY_PATH')); echo json_last_error() === JSON_ERROR_NONE ? 'Valid JSON' : 'Invalid JSON: ' . json_last_error_msg();" 2>/dev/null; then
    echo "✅ JSON is valid"
else
    echo "❌ JSON is INVALID"
    exit 1
fi

echo ""

# Check 4: Required JSON Fields
echo "4. Checking Required JSON Fields..."
echo "-----------------------------------"
HAS_EMAIL=$(php -r "\$data = json_decode(file_get_contents('$KEY_PATH'), true); echo isset(\$data['client_email']) ? 'Yes' : 'No';" 2>/dev/null)
HAS_KEY=$(php -r "\$data = json_decode(file_get_contents('$KEY_PATH'), true); echo isset(\$data['private_key']) ? 'Yes' : 'No';" 2>/dev/null)
HAS_PROJECT=$(php -r "\$data = json_decode(file_get_contents('$KEY_PATH'), true); echo isset(\$data['project_id']) ? 'Yes' : 'No';" 2>/dev/null)

echo "Has client_email: $HAS_EMAIL"
echo "Has private_key: $HAS_KEY"
echo "Has project_id: $HAS_PROJECT"

if [ "$HAS_EMAIL" = "No" ] || [ "$HAS_KEY" = "No" ]; then
    echo "❌ Missing required fields in JSON"
    exit 1
fi

echo ""

# Check 5: PHP Classes
echo "5. Checking PHP Classes..."
echo "-----------------------------------"
php -r "echo class_exists('Google_Client') ? '✅ Google_Client exists\n' : '❌ Google_Client NOT FOUND\n';" 2>/dev/null
php -r "echo class_exists('Google_Service_Walletobjects') ? '✅ Google_Service_Walletobjects exists\n' : '❌ Google_Service_Walletobjects NOT FOUND\n';" 2>/dev/null

echo ""

# Check 6: OpenSSL
echo "6. Checking OpenSSL..."
echo "-----------------------------------"
if php -m | grep -q openssl; then
    echo "✅ OpenSSL extension is enabled"
else
    echo "❌ OpenSSL extension is NOT enabled"
    echo "   Install with: sudo apt-get install php-openssl"
fi

echo ""

# Check 7: Laravel Config
echo "7. Checking Laravel Config..."
echo "-----------------------------------"
php artisan tinker --execute="
echo 'Issuer ID: ' . config('services.google_wallet.issuer_id') . PHP_EOL;
echo 'Class ID: ' . config('services.google_wallet.class_id') . PHP_EOL;
echo 'Key Path: ' . config('services.google_wallet.service_account_key') . PHP_EOL;
" 2>&1 | grep -v "Psy Shell"

echo ""

# Check 8: Try to Create Service
echo "8. Testing Service Creation..."
echo "-----------------------------------"
php artisan tinker --execute="
try {
    \$service = new \App\Services\Wallet\GoogleWalletPassService();
    echo '✅ Service created successfully' . PHP_EOL;
} catch (\Exception \$e) {
    echo '❌ Error: ' . \$e->getMessage() . PHP_EOL;
    echo '   File: ' . \$e->getFile() . ':' . \$e->getLine() . PHP_EOL;
}
" 2>&1 | grep -v "Psy Shell"

echo ""
echo "=========================================="
echo "Check Laravel logs for more details:"
echo "  tail -50 storage/logs/laravel.log"
echo "=========================================="
