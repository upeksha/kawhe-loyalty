#!/bin/bash

echo "=== Checking Real Apple Wallet Registrations ==="
echo ""

php artisan tinker --execute="
\$count = \App\Models\AppleWalletRegistration::where('active', true)->count();
echo 'Total active registrations: ' . \$count . PHP_EOL;
echo PHP_EOL;

if (\$count > 0) {
    \$regs = \App\Models\AppleWalletRegistration::where('active', true)
        ->orderBy('created_at', 'desc')
        ->get();
    
    echo 'All registrations:' . PHP_EOL;
    foreach (\$regs as \$reg) {
        echo '  ┌─────────────────────────────────────' . PHP_EOL;
        echo '  │ ID: ' . \$reg->id . PHP_EOL;
        echo '  │ Device: ' . \$reg->device_library_identifier . PHP_EOL;
        echo '  │ Serial: ' . \$reg->serial_number . PHP_EOL;
        echo '  │ Pass Type: ' . \$reg->pass_type_identifier . PHP_EOL;
        echo '  │ Push Token: ' . substr(\$reg->push_token, 0, 30) . '...' . PHP_EOL;
        echo '  │ Loyalty Account ID: ' . (\$reg->loyalty_account_id ?? 'N/A') . PHP_EOL;
        echo '  │ Registered: ' . \$reg->last_registered_at . PHP_EOL;
        echo '  │ Created: ' . \$reg->created_at . PHP_EOL;
        echo '  └─────────────────────────────────────' . PHP_EOL;
        echo '';
    }
    
    // Check for the specific device from nginx logs
    \$realDevice = \App\Models\AppleWalletRegistration::where('device_library_identifier', '76255b51d3fb8d6abb766fd40c11fe1d')
        ->where('serial_number', 'kawhe-2-9')
        ->first();
    
    if (\$realDevice) {
        echo '✅ Real Apple Wallet device registration confirmed!' . PHP_EOL;
        echo '   Device: ' . \$realDevice->device_library_identifier . PHP_EOL;
        echo '   Serial: ' . \$realDevice->serial_number . PHP_EOL;
        echo '   Push Token: ' . substr(\$realDevice->push_token, 0, 30) . '...' . PHP_EOL;
    }
} else {
    echo 'No registrations found.' . PHP_EOL;
}
" 2>/dev/null

echo ""
echo "=== Check Complete ==="
