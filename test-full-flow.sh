#!/bin/bash

cd /var/www/kawhe

echo "=== Full Real-World Test Flow ==="
echo ""

echo "Step 1: Create Merchant Account"
echo "  1. Go to: https://app.kawhe.shop/register"
echo "  2. Create account and store"
echo "  3. Note your store ID from dashboard"
echo ""

echo "Step 2: Create Customer Card"
echo "  1. Get store ID:"
STORE_ID=$(php artisan tinker --execute="
\$store = \App\Models\Store::first();
if (\$store) {
    echo \$store->id . PHP_EOL;
    echo \$store->name . PHP_EOL;
} else {
    echo 'No stores found. Create one first.';
}
" 2>/dev/null | head -1)

if [ -n "$STORE_ID" ] && [ "$STORE_ID" != "No" ]; then
    STORE_NAME=$(php artisan tinker --execute="
    \$store = \App\Models\Store::first();
    if (\$store) echo \$store->name;
    " 2>/dev/null | tail -1)
    echo "  Store: $STORE_NAME (ID: $STORE_ID)"
    echo "  2. Join URL: https://app.kawhe.shop/join/$STORE_ID"
    echo "  3. Open on iPhone and create card"
else
    echo "  ⚠️  No stores found. Create one first via web app."
fi
echo ""

echo "Step 3: Add to Wallet"
echo "  1. On card page, tap 'Add to Apple Wallet'"
echo "  2. Wait 10-20 seconds for registration"
echo ""

echo "Step 4: Test Stamping"
echo "  Option A: Use merchant scanner at https://app.kawhe.shop/scanner"
echo "  Option B: Manual stamp via tinker (see FULL_REAL_WORLD_TEST.md)"
echo ""

echo "Step 5: Monitor Logs"
echo "  tail -f storage/logs/laravel.log | grep -i push"
echo ""

echo "=== Quick Commands ==="
echo ""
echo "Check latest account:"
echo "  php artisan tinker --execute=\"\$a = \App\Models\LoyaltyAccount::latest()->first(); if (\$a) { echo 'ID: ' . \$a->id . ', Token: ' . \$a->public_token . ', URL: ' . config('app.url') . '/c/' . \$a->public_token . PHP_EOL; }\""
echo ""
echo "Check registration:"
echo "  php artisan tinker --execute=\"\$a = \App\Models\LoyaltyAccount::latest()->first(); if (\$a) { \$s = 'kawhe-' . \$a->store_id . '-' . \$a->customer_id; \$c = \App\Models\AppleWalletRegistration::where('serial_number', \$s)->where('active', true)->count(); echo 'Registrations: ' . \$c . PHP_EOL; }\""
echo ""
echo "Test push:"
echo "  php artisan wallet:apns-test kawhe-{store_id}-{customer_id}"
echo ""
echo "Stamp account:"
echo "  php artisan tinker"
echo "  Then:"
echo "  \$account = \App\Models\LoyaltyAccount::latest()->first();"
echo "  \$store = \$account->store;"
echo "  \$user = \$store->user;"
echo "  \$service = app(\App\Services\Loyalty\StampLoyaltyService::class);"
echo "  \$result = \$service->stamp(\$account, \$user, 1);"
echo "  echo 'Stamped! Count: ' . \$result->stampCount;"
echo ""
