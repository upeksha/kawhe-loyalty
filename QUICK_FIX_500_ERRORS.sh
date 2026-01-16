#!/bin/bash

# Quick Fix Script for 500 Errors
# Run this on your server: bash QUICK_FIX_500_ERRORS.sh

echo "=== Kawhe 500 Error Quick Fix ==="
echo ""

cd /var/www/kawhe || exit 1

echo "1. Pulling latest changes..."
git pull

echo ""
echo "2. Clearing all caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo ""
echo "3. Regenerating autoload..."
composer dump-autoload --no-interaction

echo ""
echo "4. Checking migrations..."
php artisan migrate:status | grep -E "Pending|subscription|customer_columns"

echo ""
echo "5. Running pending migrations..."
php artisan migrate --force

echo ""
echo "6. Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || chown -R www-data:www-data storage bootstrap/cache

echo ""
echo "7. Checking database schema..."
php artisan tinker --execute="
try {
    \$hasStripeId = \Schema::hasColumn('users', 'stripe_id');
    echo \$hasStripeId ? '✓ stripe_id column exists\n' : '✗ stripe_id column MISSING - run: php artisan migrate --force\n';
    
    \$hasSubs = \Schema::hasTable('subscriptions');
    echo \$hasSubs ? '✓ subscriptions table exists\n' : '✗ subscriptions table MISSING - run: php artisan migrate --force\n';
    
    // Check if any stores have no owner
    \$storesWithoutOwner = \App\Models\Store::whereNull('user_id')->count();
    if (\$storesWithoutOwner > 0) {
        echo '⚠ WARNING: ' . \$storesWithoutOwner . ' store(s) have no owner (user_id is null)\n';
    } else {
        echo '✓ All stores have owners\n';
    }
} catch (\Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage() . '\n';
}
"

echo ""
echo "8. Testing a store..."
php artisan tinker --execute="
\$store = \App\Models\Store::first();
if (\$store) {
    echo 'Store: ' . \$store->name . '\n';
    echo 'Store owner: ' . (\$store->user ? \$store->user->email : 'MISSING!') . '\n';
} else {
    echo 'No stores found\n';
}
"

echo ""
echo "=== Fix Complete ==="
echo ""
echo "Next steps:"
echo "1. Check the error log: tail -50 storage/logs/laravel.log"
echo "2. Try creating a card again"
echo "3. If still failing, check: tail -100 storage/logs/laravel.log | head -50"
