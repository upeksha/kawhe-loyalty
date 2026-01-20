#!/bin/bash

echo "=== Apple Wallet Push Notification Debug ==="
echo ""

# 1. Check configuration
echo "1. Checking Push Notification Configuration..."
PUSH_ENABLED=$(php artisan config:show wallet.apple.push_enabled 2>/dev/null | tail -1 | awk '{print $NF}')
APNS_KEY_ID=$(php artisan config:show wallet.apple.apns_key_id 2>/dev/null | tail -1 | awk '{print $NF}')
APNS_TEAM_ID=$(php artisan config:show wallet.apple.apns_team_id 2>/dev/null | tail -1 | awk '{print $NF}')
APNS_AUTH_KEY_PATH=$(php artisan config:show wallet.apple.apns_auth_key_path 2>/dev/null | tail -1 | awk '{print $NF}')
APNS_TOPIC=$(php artisan config:show wallet.apple.apns_topic 2>/dev/null | tail -1 | awk '{print $NF}')
APNS_PRODUCTION=$(php artisan config:show wallet.apple.apns_production 2>/dev/null | tail -1 | awk '{print $NF}')

echo "   Push Enabled: $PUSH_ENABLED"
echo "   APNs Key ID: $APNS_KEY_ID"
echo "   APNs Team ID: $APNS_TEAM_ID"
echo "   APNs Auth Key Path: $APNS_AUTH_KEY_PATH"
echo "   APNs Topic: $APNS_TOPIC"
echo "   APNs Production: $APNS_PRODUCTION"
echo ""

# 2. Check if auth key file exists
echo "2. Checking APNs Auth Key File..."
if [ -n "$APNS_AUTH_KEY_PATH" ]; then
    FULL_PATH=$(php artisan tinker --execute="
        \$path = '$APNS_AUTH_KEY_PATH';
        if (!str_starts_with(\$path, '/')) {
            echo storage_path('app/private/' . \$path);
        } else {
            echo \$path;
        }
    " 2>/dev/null | tail -1)
    
    if [ -f "$FULL_PATH" ]; then
        echo "   ✅ File exists: $FULL_PATH"
        ls -lh "$FULL_PATH" | awk '{print "   Size: " $5 " Permissions: " $1}'
    else
        echo "   ❌ File NOT found: $FULL_PATH"
    fi
else
    echo "   ❌ APNs auth key path not configured"
fi
echo ""

# 3. Check registrations
echo "3. Checking Active Registrations..."
REG_COUNT=$(php artisan tinker --execute="
    echo \App\Models\AppleWalletRegistration::where('active', true)->count();
" 2>/dev/null | tail -1)

echo "   Active registrations: $REG_COUNT"

if [ "$REG_COUNT" -gt 0 ]; then
    echo "   Recent registrations:"
    php artisan tinker --execute="
        \$regs = \App\Models\AppleWalletRegistration::where('active', true)->take(3)->get();
        foreach (\$regs as \$reg) {
            echo '     - Serial: ' . \$reg->serial_number . ', Device: ' . substr(\$reg->device_library_identifier, 0, 20) . '..., Push Token: ' . substr(\$reg->push_token, 0, 20) . '...' . PHP_EOL;
        }
    " 2>/dev/null | tail -5
fi
echo ""

# 4. Check recent logs for push attempts
echo "4. Recent Push Notification Logs (last 20 lines)..."
tail -n 100 storage/logs/laravel.log 2>/dev/null | grep -i "push\|apns\|wallet.*sync" | tail -10 || echo "   No recent push logs found"
echo ""

# 5. Check if jobs are being dispatched
echo "5. Checking Queue Status..."
QUEUE_DRIVER=$(php artisan config:show queue.default 2>/dev/null | tail -1 | awk '{print $NF}')
echo "   Queue Driver: $QUEUE_DRIVER"

if [ "$QUEUE_DRIVER" = "sync" ]; then
    echo "   ⚠️  Queue is set to 'sync' - jobs run immediately (this is OK)"
else
    echo "   ℹ️  Queue is set to '$QUEUE_DRIVER' - make sure queue worker is running"
    echo "   Check with: php artisan queue:work"
fi
echo ""

# 6. Test push service directly
echo "6. Testing Push Service (if enabled)..."
if [ "$PUSH_ENABLED" = "true" ]; then
    echo "   Attempting to test push service..."
    php artisan tinker --execute="
        try {
            \$account = \App\Models\LoyaltyAccount::first();
            if (!\$account) {
                echo 'No loyalty accounts found' . PHP_EOL;
                exit;
            }
            
            \$service = app(\App\Services\Wallet\Apple\ApplePushService::class);
            \$passType = config('passgenerator.pass_type_identifier');
            \$serial = 'kawhe-' . \$account->store_id . '-' . \$account->customer_id;
            
            echo 'Testing push for: ' . \$serial . PHP_EOL;
            \$service->sendPassUpdatePushes(\$passType, \$serial);
            echo 'Push service called (check logs above for results)' . PHP_EOL;
        } catch (Exception \$e) {
            echo 'Error: ' . \$e->getMessage() . PHP_EOL;
        }
    " 2>&1 | tail -10
else
    echo "   ⚠️  Push notifications are disabled in config"
fi
echo ""

# 7. Check for errors
echo "7. Recent Errors..."
tail -n 100 storage/logs/laravel.log 2>/dev/null | grep -i "error\|exception\|failed" | grep -i "push\|apns\|wallet" | tail -5 || echo "   No recent errors found"
echo ""

echo "=== Debug Complete ==="
echo ""
echo "Next steps:"
echo "1. If push is disabled, set WALLET_APPLE_PUSH_ENABLED=true in .env"
echo "2. If auth key file is missing, upload it to storage/app/private/apns/"
echo "3. If no registrations, add a pass to Apple Wallet first"
echo "4. Check logs above for specific error messages"
echo "5. Test stamping a loyalty account and watch logs: tail -f storage/logs/laravel.log | grep -i push"
