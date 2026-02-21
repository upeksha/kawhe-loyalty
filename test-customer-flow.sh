#!/bin/bash

cd /var/www/kawhe

echo "=== Customer Flow Test ==="
echo ""

# Get or create account
echo "1. Getting test account..."
ACCOUNT=$(php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::first();
if (!\$account) {
    echo 'No accounts found. Run: php test-wallet-quick.php';
    exit(1);
}
echo \$account->public_token . PHP_EOL;
echo \$account->id . PHP_EOL;
echo \$account->store_id . PHP_EOL;
echo \$account->customer_id . PHP_EOL;
" 2>/dev/null | tail -4)

if [ -z "$ACCOUNT" ]; then
    echo "❌ No accounts found. Creating one..."
    php test-wallet-quick.php
    ACCOUNT=$(php artisan tinker --execute="
    \$account = \App\Models\LoyaltyAccount::first();
    echo \$account->public_token . PHP_EOL;
    echo \$account->id . PHP_EOL;
    echo \$account->store_id . PHP_EOL;
    echo \$account->customer_id . PHP_EOL;
    " 2>/dev/null | tail -4)
fi

PUBLIC_TOKEN=$(echo "$ACCOUNT" | head -1)
ACCOUNT_ID=$(echo "$ACCOUNT" | head -2 | tail -1)
STORE_ID=$(echo "$ACCOUNT" | head -3 | tail -1)
CUSTOMER_ID=$(echo "$ACCOUNT" | tail -1)

SERIAL="kawhe-${STORE_ID}-${CUSTOMER_ID}"

echo "Account ID: $ACCOUNT_ID"
echo "Public Token: $PUBLIC_TOKEN"
echo "Serial: $SERIAL"
echo ""

# Card URL
CARD_URL="https://app.kawhe.shop/c/${PUBLIC_TOKEN}"
echo "2. Card URL:"
echo "   $CARD_URL"
echo "   👆 Open this on your iPhone to add to Wallet"
echo ""

# Check registration
echo "3. Checking device registration..."
echo "   (Add pass to Wallet first, then wait 10 seconds)"
sleep 2
REG_COUNT=$(php artisan tinker --execute="
\$reg = \App\Models\AppleWalletRegistration::where('serial_number', '$SERIAL')->where('active', true)->count();
echo \$reg;
" 2>/dev/null | tail -1)

if [ "$REG_COUNT" -gt "0" ]; then
    echo "   ✓ Device registered!"
else
    echo "   ⚠️  No registration yet. Add pass to Wallet first, then check again."
fi
echo ""

# Test push
echo "4. Testing push notification..."
php artisan wallet:apns-test "$SERIAL" 2>/dev/null
echo ""

# Instructions
echo "=== Next Steps ==="
echo ""
echo "1. 📱 Open this URL on your iPhone:"
echo "   $CARD_URL"
echo ""
echo "2. ➕ Tap 'Add to Apple Wallet'"
echo ""
echo "3. ⏱️  Wait 10 seconds for device registration"
echo ""
echo "4. 🎫 Open Wallet app and view the pass"
echo ""
echo "5. 📝 Stamp the account:"
echo "   php artisan tinker"
echo "   Then run:"
echo "   \$account = \App\Models\LoyaltyAccount::find($ACCOUNT_ID);"
echo "   \$store = \$account->store;"
echo "   \$user = \$store->user;"
echo "   \$service = app(\App\Services\Loyalty\StampLoyaltyService::class);"
echo "   \$result = \$service->stamp(\$account, \$user, 1);"
echo "   echo 'Stamped! Count: ' . \$result->stampCount;"
echo ""
echo "6. 👀 Watch Wallet app - pass should update automatically!"
echo ""
echo "7. 📊 Monitor logs:"
echo "   tail -f storage/logs/laravel.log | grep -i push"
echo ""
