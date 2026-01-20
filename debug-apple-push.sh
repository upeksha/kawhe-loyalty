#!/bin/bash

echo "=== Apple Wallet Push Notification Debug Script ==="
echo ""

# 1. Check configuration
echo "1. Checking Configuration..."
php artisan config:show wallet.apple.push_enabled
php artisan config:show wallet.apple.apns_key_id
php artisan config:show wallet.apple.apns_team_id
php artisan config:show wallet.apple.apns_auth_key_path
php artisan config:show wallet.apple.apns_topic
php artisan config:show wallet.apple.apns_production
echo ""

# 2. Check APNs key file
echo "2. Checking APNs Key File..."
KEY_PATH=$(php artisan tinker --execute="echo storage_path('app/private/' . config('wallet.apple.apns_auth_key_path'));")
echo "Expected path: $KEY_PATH"
if [ -f "$KEY_PATH" ]; then
    echo "✅ File exists"
    ls -la "$KEY_PATH"
    echo "File size: $(stat -f%z "$KEY_PATH" 2>/dev/null || stat -c%s "$KEY_PATH" 2>/dev/null) bytes"
else
    echo "❌ File NOT found at: $KEY_PATH"
fi
echo ""

# 3. Check cURL HTTP/2 support
echo "3. Checking cURL HTTP/2 Support..."
curl --version | grep -i "http"
if curl --version | grep -q "HTTP/2"; then
    echo "✅ HTTP/2 supported"
else
    echo "⚠️  HTTP/2 may not be supported"
fi
echo ""

# 4. Check registered devices
echo "4. Checking Registered Devices..."
php artisan tinker --execute="
\$count = \App\Models\AppleWalletRegistration::where('active', true)->count();
echo 'Active registrations: ' . \$count . PHP_EOL;
if (\$count > 0) {
    \$regs = \App\Models\AppleWalletRegistration::where('active', true)->take(3)->get();
    foreach (\$regs as \$reg) {
        echo '  - Device: ' . \$reg->device_library_identifier . ', Serial: ' . \$reg->serial_number . PHP_EOL;
    }
}
"
echo ""

# 5. Test JWT generation (if possible)
echo "5. Testing JWT Generation..."
php artisan tinker --execute="
try {
    \$service = app(\App\Services\Wallet\Apple\ApplePushService::class);
    // Use reflection to test JWT generation
    \$reflection = new ReflectionClass(\$service);
    \$method = \$reflection->getMethod('generateJWT');
    \$method->setAccessible(true);
    \$jwt = \$method->invoke(\$service);
    echo '✅ JWT generated successfully' . PHP_EOL;
    echo 'JWT preview: ' . substr(\$jwt, 0, 50) . '...' . PHP_EOL;
} catch (Exception \$e) {
    echo '❌ JWT generation failed: ' . \$e->getMessage() . PHP_EOL;
}
" 2>&1
echo ""

# 6. Check recent logs
echo "6. Recent Wallet/APNs Logs (last 20 lines)..."
tail -n 20 storage/logs/laravel.log | grep -i "wallet\|apns\|push" || echo "No recent wallet/APNs logs found"
echo ""

# 7. Check for errors
echo "7. Recent Errors..."
tail -n 50 storage/logs/laravel.log | grep -i "error\|exception\|failed" | grep -i "wallet\|apns\|push" | tail -5 || echo "No recent errors found"
echo ""

echo "=== Debug Complete ==="
echo ""
echo "Next steps:"
echo "1. If JWT generation failed, check the APNs key file format"
echo "2. If no registrations found, register a device first"
echo "3. If HTTP/2 not supported, update cURL"
echo "4. Check logs above for specific error messages"
