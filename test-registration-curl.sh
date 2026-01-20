#!/bin/bash

# Quick test script for Apple Wallet registration endpoint

BASE_URL="${1:-https://testing.kawhe.shop}"

echo "=== Testing Apple Wallet Registration Endpoint ==="
echo ""

# Get account details
echo "1. Getting test account..."
ACCOUNT_INFO=$(php artisan tinker --execute="
\$account = \App\Models\LoyaltyAccount::first();
if (!\$account) {
    echo 'NO_ACCOUNT';
    exit;
}
\$serial = 'kawhe-' . \$account->store_id . '-' . \$account->customer_id;
echo \$serial . '|' . \$account->public_token;
" 2>/dev/null | tail -1)

if [ "$ACCOUNT_INFO" = "NO_ACCOUNT" ] || [ -z "$ACCOUNT_INFO" ]; then
    echo "❌ No loyalty accounts found. Create one first."
    exit 1
fi

SERIAL=$(echo "$ACCOUNT_INFO" | cut -d'|' -f1)
TOKEN=$(echo "$ACCOUNT_INFO" | cut -d'|' -f2)
PASS_TYPE=$(php artisan config:show passgenerator.pass_type_identifier 2>/dev/null | tail -1 | awk '{print $NF}')

echo "   Serial: $SERIAL"
echo "   Token: ${TOKEN:0:20}..."
echo "   Pass Type: $PASS_TYPE"
echo ""

# Test registration
echo "2. Testing registration endpoint..."
DEVICE_ID="test-device-$(date +%s)"
PUSH_TOKEN="test-push-$(date +%s)"

HTTP_CODE=$(curl -s -o /tmp/curl_response.txt -w "%{http_code}" -X POST "$BASE_URL/wallet/v1/devices/$DEVICE_ID/registrations/$PASS_TYPE/$SERIAL" \
  -H "Content-Type: application/json" \
  -H "Authorization: ApplePass $TOKEN" \
  -d "{\"pushToken\":\"$PUSH_TOKEN\"}")

RESPONSE=$(cat /tmp/curl_response.txt)
rm -f /tmp/curl_response.txt

if [ "$HTTP_CODE" = "201" ] || [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Registration successful (HTTP $HTTP_CODE)"
    
    # Check database
    REG_COUNT=$(php artisan tinker --execute="
        echo \App\Models\AppleWalletRegistration::where('device_library_identifier', '$DEVICE_ID')
            ->where('serial_number', '$SERIAL')
            ->count();
    " 2>/dev/null | tail -1)
    
    if [ "$REG_COUNT" = "1" ]; then
        echo "✅ Registration record created in database"
    else
        echo "⚠️  Registration record not found (count: $REG_COUNT)"
    fi
else
    echo "❌ Registration failed (HTTP $HTTP_CODE)"
    echo "Response: $RESPONSE"
    echo ""
    echo "Check logs: tail -n 20 storage/logs/laravel.log | grep -i 'wallet\|registration'"
fi

echo ""
echo "=== Test Complete ==="
