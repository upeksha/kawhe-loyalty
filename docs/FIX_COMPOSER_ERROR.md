# Fix Composer Error After Installing google/apiclient

## Problem
The `composer install --no-dev` command failed because it's trying to remove dev dependencies while the autoloader is still trying to use them.

## Solution

Run these commands **separately** (not on the same line):

```bash
# Step 1: The require already succeeded, so skip it
# composer require google/apiclient  # Already done

# Step 2: Regenerate autoloader first (without removing dev deps)
composer dump-autoload --optimize

# Step 3: Now install without dev dependencies
composer install --no-dev --optimize-autoloader
```

**OR** simpler approach:

```bash
# Just regenerate autoloader (google/apiclient is already installed)
composer dump-autoload --optimize --no-dev

# Clear Laravel caches
php artisan config:clear
php artisan config:cache
```

## Quick Fix (Recommended)

Since `google/apiclient` is already installed (the require succeeded), just regenerate the autoloader:

```bash
composer dump-autoload --optimize
php artisan config:clear
php artisan config:cache
```

Then test Google Wallet again.
