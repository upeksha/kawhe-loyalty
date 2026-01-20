#!/bin/bash

echo "=== APNs 403 Error Debugging ==="
echo ""

echo "1. Checking APNs Configuration..."
php artisan config:show wallet.apple.apns_key_id
php artisan config:show wallet.apple.apns_team_id
php artisan config:show wallet.apple.apns_auth_key_path
php artisan config:show wallet.apple.apns_topic
php artisan config:show wallet.apple.apns_production
php artisan config:show passgenerator.pass_type_identifier
echo ""

echo "2. Checking APNs Auth Key File..."
AUTH_KEY_PATH=$(php artisan config:show wallet.apple.apns_auth_key_path 2>/dev/null | tail -1 | awk '{print $NF}')
if [ -n "$AUTH_KEY_PATH" ]; then
    FULL_PATH=$(php artisan tinker --execute="
        \$path = '$AUTH_KEY_PATH';
        if (!str_starts_with(\$path, '/')) {
            echo storage_path('app/private/' . \$path);
        } else {
            echo \$path;
        }
    " 2>/dev/null | tail -1)
    
    if [ -f "$FULL_PATH" ]; then
        echo "✅ File exists: $FULL_PATH"
        ls -lh "$FULL_PATH"
        echo ""
        echo "File content preview (first 3 lines):"
        head -3 "$FULL_PATH"
    else
        echo "❌ File NOT found: $FULL_PATH"
    fi
else
    echo "❌ APNs auth key path not configured"
fi
echo ""

echo "3. Verifying Topic Matches Pass Type Identifier..."
APNS_TOPIC=$(php artisan config:show wallet.apple.apns_topic 2>/dev/null | tail -1 | awk '{print $NF}')
PASS_TYPE=$(php artisan config:show passgenerator.pass_type_identifier 2>/dev/null | tail -1 | awk '{print $NF}')

if [ "$APNS_TOPIC" = "$PASS_TYPE" ]; then
    echo "✅ Topic matches: $APNS_TOPIC"
else
    echo "⚠️  Topic mismatch!"
    echo "   APNs Topic: $APNS_TOPIC"
    echo "   Pass Type: $PASS_TYPE"
    echo "   These should match!"
fi
echo ""

echo "4. Recent APNs Errors (last 5)..."
tail -n 100 storage/logs/laravel.log 2>/dev/null | grep -i "push notification failed\|apns.*403\|apns.*error" | tail -5 || echo "No recent APNs errors found"
echo ""

echo "5. Testing JWT Generation..."
php artisan tinker --execute="
try {
    \$service = app(\App\Services\Wallet\Apple\ApplePushService::class);
    \$reflection = new ReflectionClass(\$service);
    \$method = \$reflection->getMethod('generateJWT');
    \$method->setAccessible(true);
    \$jwt = \$method->invoke(\$service);
    echo '✅ JWT generated successfully' . PHP_EOL;
    echo 'JWT length: ' . strlen(\$jwt) . PHP_EOL;
    echo 'JWT preview: ' . substr(\$jwt, 0, 50) . '...' . PHP_EOL;
    
    // Decode JWT header to verify
    \$parts = explode('.', \$jwt);
    if (count(\$parts) >= 2) {
        \$header = json_decode(base64_decode(strtr(\$parts[0], '-_', '+/')), true);
        echo 'JWT Header: ' . json_encode(\$header) . PHP_EOL;
    }
} catch (Exception \$e) {
    echo '❌ JWT generation failed: ' . \$e->getMessage() . PHP_EOL;
}
" 2>&1 | tail -10
echo ""

echo "=== Common 403 Causes ==="
echo ""
echo "1. APNs Key doesn't have permission for the topic"
echo "   → Check Apple Developer Portal: Certificates, Identifiers & Profiles"
echo "   → Ensure APNs key has 'Apple Push Notifications service (APNs)' enabled"
echo "   → Topic must match Pass Type Identifier exactly"
echo ""
echo "2. Wrong APNs endpoint (production vs sandbox)"
echo "   → Check APPLE_APNS_PRODUCTION in .env"
echo "   → Production: https://api.push.apple.com"
echo "   → Sandbox: https://api.sandbox.push.apple.com"
echo ""
echo "3. Invalid JWT token"
echo "   → Check APNs Key ID, Team ID, and auth key file"
echo "   → Ensure auth key file is readable"
echo ""
echo "4. Topic mismatch"
echo "   → APNs topic must exactly match Pass Type Identifier"
echo "   → Current topic: $APNS_TOPIC"
echo "   → Current pass type: $PASS_TYPE"
echo ""
