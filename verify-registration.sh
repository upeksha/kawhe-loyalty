#!/bin/bash

echo "=== Verifying Apple Wallet Registration ==="
echo ""

# Check database
echo "1. Checking database registrations..."
php artisan tinker --execute="
\$count = \App\Models\AppleWalletRegistration::where('active', true)->count();
echo 'Total active registrations: ' . \$count . PHP_EOL;
echo PHP_EOL;

if (\$count > 0) {
    \$regs = \App\Models\AppleWalletRegistration::where('active', true)->orderBy('created_at', 'desc')->take(5)->get();
    echo 'Recent registrations:' . PHP_EOL;
    foreach (\$regs as \$reg) {
        echo '  - ID: ' . \$reg->id . PHP_EOL;
        echo '    Device: ' . \$reg->device_library_identifier . PHP_EOL;
        echo '    Serial: ' . \$reg->serial_number . PHP_EOL;
        echo '    Pass Type: ' . \$reg->pass_type_identifier . PHP_EOL;
        echo '    Push Token: ' . substr(\$reg->push_token, 0, 20) . '...' . PHP_EOL;
        echo '    Loyalty Account ID: ' . \$reg->loyalty_account_id . PHP_EOL;
        echo '    Registered: ' . \$reg->last_registered_at . PHP_EOL;
        echo '';
    }
}
" 2>/dev/null

echo ""
echo "2. Recent registration logs..."
tail -n 30 storage/logs/laravel.log 2>/dev/null | grep -i "registration\|wallet.*device" | tail -5 || echo "No recent registration logs found"
echo ""

echo "=== Verification Complete ==="
