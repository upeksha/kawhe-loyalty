#!/bin/bash

# APNs Push Configuration Diagnostic Script
# Run: bash check-apns.sh

cd /var/www/kawhe

echo "=========================================="
echo "APNs Push Configuration Diagnostic"
echo "=========================================="
echo ""

# Check .env variables
echo "=== .env Configuration ==="
if [ -f .env ]; then
    echo "WALLET_APPLE_PUSH_ENABLED: $(grep WALLET_APPLE_PUSH_ENABLED .env | cut -d '=' -f2)"
    echo "APPLE_APNS_KEY_ID: $(grep APPLE_APNS_KEY_ID .env | cut -d '=' -f2)"
    echo "APPLE_APNS_TEAM_ID: $(grep APPLE_APNS_TEAM_ID .env | cut -d '=' -f2)"
    echo "APPLE_APNS_AUTH_KEY_PATH: $(grep APPLE_APNS_AUTH_KEY_PATH .env | cut -d '=' -f2)"
    echo "APPLE_APNS_TOPIC: $(grep APPLE_APNS_TOPIC .env | cut -d '=' -f2)"
    echo "APPLE_APNS_USE_SANDBOX: $(grep APPLE_APNS_USE_SANDBOX .env | cut -d '=' -f2)"
else
    echo "❌ .env file not found!"
fi
echo ""

# Check config cache
echo "=== Laravel Config ==="
php artisan config:show wallet.apple.push_enabled 2>/dev/null | tail -1
php artisan config:show wallet.apple.apns_key_id 2>/dev/null | tail -1
php artisan config:show wallet.apple.apns_team_id 2>/dev/null | tail -1
php artisan config:show wallet.apple.apns_topic 2>/dev/null | tail -1
php artisan config:show wallet.apple.apns_production 2>/dev/null | tail -1
echo ""

# Check key file
echo "=== APNs Key File ==="
KEY_PATH=$(php artisan tinker --execute="echo config('wallet.apple.apns_auth_key_path');" 2>/dev/null | tail -1)
if [ -z "$KEY_PATH" ]; then
    KEY_PATH="storage/app/private/apns/AuthKey_5JGMHRZC36.p8"
fi

# Resolve full path
if [[ ! "$KEY_PATH" =~ ^/ ]]; then
    KEY_PATH="storage/app/private/$KEY_PATH"
fi

echo "Key path: $KEY_PATH"
if [ -f "$KEY_PATH" ]; then
    echo "✓ File exists"
    ls -lh "$KEY_PATH" | awk '{print "  Size: " $5 ", Permissions: " $1}'
    if [ -r "$KEY_PATH" ]; then
        echo "✓ File is readable"
    else
        echo "❌ File is NOT readable!"
        echo "  Fix: chmod 600 $KEY_PATH"
    fi
else
    echo "❌ File NOT found!"
    echo "  Expected location: $KEY_PATH"
    echo "  Check if file exists in:"
    find . -name "*.p8" -type f 2>/dev/null | head -5
fi
echo ""

# Check registrations
echo "=== Device Registrations ==="
REG_COUNT=$(php artisan tinker --execute="echo \App\Models\AppleWalletRegistration::where('active', true)->count();" 2>/dev/null | tail -1)
echo "Active registrations: $REG_COUNT"
if [ "$REG_COUNT" -eq "0" ]; then
    echo "⚠️  No devices registered. Add pass to iPhone Wallet first."
else
    echo "✓ Devices registered"
fi
echo ""

# Check pass type identifier
echo "=== Pass Type Identifier ==="
PASS_TYPE=$(php artisan config:show passgenerator.pass_type_identifier 2>/dev/null | tail -1 | xargs)
APNS_TOPIC=$(php artisan config:show wallet.apple.apns_topic 2>/dev/null | tail -1 | xargs)
echo "Pass Type: $PASS_TYPE"
echo "APNs Topic: $APNS_TOPIC"
if [ "$PASS_TYPE" = "$APNS_TOPIC" ]; then
    echo "✓ Topics match"
else
    echo "❌ Topics DO NOT match!"
    echo "  These must be identical!"
fi
echo ""

# Test push if registrations exist
if [ "$REG_COUNT" -gt "0" ]; then
    echo "=== Test Push ==="
    SERIAL=$(php artisan tinker --execute="\$a = \App\Models\LoyaltyAccount::first(); if(\$a) echo 'kawhe-' . \$a->store_id . '-' . \$a->customer_id . PHP_EOL;" 2>/dev/null | tail -1)
    if [ -n "$SERIAL" ]; then
        echo "Testing serial: $SERIAL"
        php artisan wallet:apns-test "$SERIAL" 2>&1 | tail -10
    else
        echo "⚠️  No loyalty accounts found to test"
    fi
else
    echo "=== Test Push ==="
    echo "⚠️  Skipping - no registrations found"
    echo "  Add pass to iPhone Wallet first, then run:"
    echo "  php artisan wallet:apns-test kawhe-1-10"
fi
echo ""

echo "=========================================="
echo "Diagnostic Complete"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Fix any issues shown above"
echo "2. Clear config cache: php artisan config:clear"
echo "3. Test push: php artisan wallet:apns-test {serial}"
echo "4. Check logs: tail -f storage/logs/laravel.log | grep -i push"
