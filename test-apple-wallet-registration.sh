#!/bin/bash

echo "=== Apple Wallet Registration Testing Script ==="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

BASE_URL="${1:-https://testing.kawhe.shop}"
AUTH_TOKEN="${2:-}"

if [ -z "$AUTH_TOKEN" ]; then
    echo -e "${YELLOW}Usage: $0 [BASE_URL] [AUTH_TOKEN]${NC}"
    echo "Example: $0 https://testing.kawhe.shop rpLceO5ApZPgVRXhDLRUH5fEaE85flvu0tXbvKoE"
    echo ""
    echo "Getting auth token from first loyalty account..."
    AUTH_TOKEN=$(php artisan tinker --execute="
        \$account = \App\Models\LoyaltyAccount::first();
        if (\$account) {
            echo \$account->public_token;
        } else {
            echo 'NO_ACCOUNT';
        }
    " 2>/dev/null | tail -1)
    
    if [ "$AUTH_TOKEN" = "NO_ACCOUNT" ] || [ -z "$AUTH_TOKEN" ]; then
        echo -e "${RED}❌ No loyalty accounts found. Create one first.${NC}"
        exit 1
    fi
fi

echo "Base URL: $BASE_URL"
echo "Auth Token: ${AUTH_TOKEN:0:20}..."
echo ""

# Get serial number from first account
echo "1. Getting test account details..."
SERIAL=$(php artisan tinker --execute="
    \$account = \App\Models\LoyaltyAccount::first();
    if (\$account) {
        echo 'kawhe-' . \$account->store_id . '-' . \$account->customer_id;
    }
" 2>/dev/null | tail -1)

PASS_TYPE=$(php artisan config:show passgenerator.pass_type_identifier 2>/dev/null | tail -1 | awk '{print $NF}')

if [ -z "$SERIAL" ] || [ -z "$PASS_TYPE" ]; then
    echo -e "${RED}❌ Failed to get account details${NC}"
    exit 1
fi

echo "   Serial Number: $SERIAL"
echo "   Pass Type: $PASS_TYPE"
echo ""

# Test 1: Check pass.json includes webServiceURL
echo "2. Checking pass.json for webServiceURL..."
PASS_PATH=$(php artisan tinker --execute="
    \$account = \App\Models\LoyaltyAccount::first();
    \$service = app(\App\Services\Wallet\AppleWalletPassService::class);
    try {
        \$passData = \$service->generatePass(\$account);
        // Save to temp file
        \$tempPath = storage_path('app/private/test-pass-' . \$account->id . '.pkpass');
        file_put_contents(\$tempPath, \$passData);
        echo \$tempPath;
    } catch (Exception \$e) {
        echo 'ERROR: ' . \$e->getMessage();
    }
" 2>/dev/null | tail -1)

if [ -f "$PASS_PATH" ]; then
    # Extract pass.json
    unzip -p "$PASS_PATH" pass.json 2>/dev/null | python3 -m json.tool > /tmp/pass.json 2>/dev/null
    if [ -f /tmp/pass.json ]; then
        WEB_SERVICE_URL=$(grep -o '"webServiceURL": "[^"]*"' /tmp/pass.json | cut -d'"' -f4)
        AUTH_TOKEN_IN_PASS=$(grep -o '"authenticationToken": "[^"]*"' /tmp/pass.json | cut -d'"' -f4)
        
        if [ -n "$WEB_SERVICE_URL" ]; then
            echo -e "   ${GREEN}✅ webServiceURL found: $WEB_SERVICE_URL${NC}"
            if [[ "$WEB_SERVICE_URL" == *"/wallet" ]] && [[ "$WEB_SERVICE_URL" != *"/wallet/v1" ]]; then
                echo -e "   ${GREEN}✅ webServiceURL format is correct (Apple will append /v1)${NC}"
            else
                echo -e "   ${RED}❌ webServiceURL format is incorrect (should end with /wallet, not /wallet/v1)${NC}"
            fi
        else
            echo -e "   ${RED}❌ webServiceURL NOT found in pass.json${NC}"
        fi
        
        if [ -n "$AUTH_TOKEN_IN_PASS" ]; then
            echo -e "   ${GREEN}✅ authenticationToken found: ${AUTH_TOKEN_IN_PASS:0:20}...${NC}"
        else
            echo -e "   ${RED}❌ authenticationToken NOT found in pass.json${NC}"
        fi
    else
        echo -e "   ${RED}❌ Failed to extract pass.json${NC}"
    fi
    rm -f "$PASS_PATH" /tmp/pass.json
else
    echo -e "   ${RED}❌ Failed to generate test pass${NC}"
fi
echo ""

# Test 2: Check routes
echo "3. Checking Laravel routes..."
ROUTES=$(php artisan route:list --path=wallet 2>/dev/null | grep -E "wallet/v1" | wc -l)
if [ "$ROUTES" -gt 0 ]; then
    echo -e "   ${GREEN}✅ Found $ROUTES wallet/v1 routes${NC}"
    php artisan route:list --path=wallet/v1 2>/dev/null | grep -E "POST|GET|DELETE" | head -5
else
    echo -e "   ${RED}❌ No wallet/v1 routes found${NC}"
fi
echo ""

# Test 3: Manual registration test
echo "4. Testing registration endpoint with curl..."
DEVICE_ID="test-device-$(date +%s)"
PUSH_TOKEN="test-push-token-$(date +%s)"

RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/wallet/v1/devices/$DEVICE_ID/registrations/$PASS_TYPE/$SERIAL" \
  -H "Content-Type: application/json" \
  -H "Authorization: ApplePass $AUTH_TOKEN" \
  -d "{\"pushToken\":\"$PUSH_TOKEN\"}" \
  -o /tmp/curl_response.txt 2>&1)

HTTP_CODE=$(echo "$RESPONSE" | tail -1)
BODY=$(cat /tmp/curl_response.txt 2>/dev/null)

if [ "$HTTP_CODE" = "201" ] || [ "$HTTP_CODE" = "200" ]; then
    echo -e "   ${GREEN}✅ Registration endpoint returned $HTTP_CODE${NC}"
    
    # Check if registration was created
    REG_COUNT=$(php artisan tinker --execute="
        echo \App\Models\AppleWalletRegistration::where('device_library_identifier', '$DEVICE_ID')
            ->where('serial_number', '$SERIAL')
            ->count();
    " 2>/dev/null | tail -1)
    
    if [ "$REG_COUNT" = "1" ]; then
        echo -e "   ${GREEN}✅ Registration record created in database${NC}"
    else
        echo -e "   ${RED}❌ Registration record NOT found in database (count: $REG_COUNT)${NC}"
    fi
else
    echo -e "   ${RED}❌ Registration endpoint returned $HTTP_CODE${NC}"
    echo "   Response: $BODY"
fi
rm -f /tmp/curl_response.txt
echo ""

# Test 4: Check logs
echo "5. Recent wallet-related logs (last 10 lines)..."
tail -n 50 storage/logs/laravel.log 2>/dev/null | grep -i "wallet\|registration" | tail -5 || echo "   No recent wallet logs found"
echo ""

# Test 5: Check database registrations
echo "6. Current registrations in database..."
REG_TOTAL=$(php artisan tinker --execute="
    echo \App\Models\AppleWalletRegistration::where('active', true)->count();
" 2>/dev/null | tail -1)
echo "   Active registrations: $REG_TOTAL"
if [ "$REG_TOTAL" -gt 0 ]; then
    php artisan tinker --execute="
        \$regs = \App\Models\AppleWalletRegistration::where('active', true)->take(3)->get();
        foreach (\$regs as \$reg) {
            echo '  - Device: ' . \$reg->device_library_identifier . ', Serial: ' . \$reg->serial_number . PHP_EOL;
        }
    " 2>/dev/null | tail -5
fi
echo ""

echo "=== Testing Complete ==="
echo ""
echo "Next steps:"
echo "1. Download a pass to an iPhone"
echo "2. Add it to Apple Wallet"
echo "3. Check nginx access.log for POST /wallet/v1/devices/.../registrations/..."
echo "4. Check database for new registration"
echo "5. Check logs for registration messages"
