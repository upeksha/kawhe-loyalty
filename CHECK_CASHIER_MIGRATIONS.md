# Check Cashier Migrations

Run this on your server to check if Cashier migrations are applied:

```bash
php artisan migrate:status | grep -E "subscription|customer_columns"
```

Or check the database directly:

```bash
php artisan tinker
```

Then run:

```php
// Check if users table has stripe_id column
$columns = \DB::select("PRAGMA table_info(users)");
$hasStripeId = collect($columns)->contains(function($col) { return $col->name === 'stripe_id'; });
echo $hasStripeId ? "✓ stripe_id column exists\n" : "✗ stripe_id column MISSING\n";

// Check if subscriptions table exists
$hasSubscriptions = \Schema::hasTable('subscriptions');
echo $hasSubscriptions ? "✓ subscriptions table exists\n" : "✗ subscriptions table MISSING\n";

// Check if subscription_items table exists
$hasItems = \Schema::hasTable('subscription_items');
echo $hasItems ? "✓ subscription_items table exists\n" : "✗ subscription_items table MISSING\n";
```

If any are missing, run:

```bash
php artisan migrate --force
```
