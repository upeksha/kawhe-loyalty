# Quick Fix: Git Pull Conflict on Live Server

## Problem
Git won't pull because you have local changes to `composer.json` that would be overwritten.

## Solution Options

### Option 1: Stash Your Changes (Recommended - Temporary)

```bash
# 1. See what you changed
git diff composer.json

# 2. Stash your changes (saves them temporarily)
git stash save "Local composer.json changes before pull"

# 3. Now pull
git pull origin main

# 4. Check if you need your stashed changes
git stash list
git stash show -p stash@{0}  # Preview what was stashed

# 5. If you need them back, restore:
git stash pop  # This may create conflicts if git also changed composer.json
```

### Option 2: Commit Your Changes (If They're Important)

```bash
# 1. See what you changed
git diff composer.json

# 2. If the changes are important, commit them
git add composer.json
git commit -m "Local composer.json changes"

# 3. Now pull (may create merge conflict)
git pull origin main

# 4. If there's a conflict, resolve it:
#    - Edit composer.json to merge changes
#    - Then: git add composer.json && git commit
```

### Option 3: Discard Your Changes (If They're Not Important)

⚠️ **WARNING**: This will permanently delete your local changes!

```bash
# 1. See what you'll lose
git diff composer.json

# 2. If you're sure, discard changes
git checkout -- composer.json

# 3. Now pull
git pull origin main
```

## Recommended Steps (Safe Approach)

```bash
# Step 1: Backup first!
cp composer.json composer.json.backup

# Step 2: See what changed
git diff composer.json

# Step 3: Stash changes
git stash save "Local changes before pull $(date +%Y%m%d_%H%M%S)"

# Step 4: Pull
git pull origin main

# Step 5: Install new dependencies (composer.json changed)
composer install --no-dev --optimize-autoloader

# Step 6: Clear and cache config
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 7: Run migrations (if any)
php artisan migrate --force

# Step 8: Check if you need your stashed changes
git stash list
# If you need them, compare:
diff composer.json composer.json.backup
```

## What Changed in composer.json?

The new version adds:
```json
"google/apiclient": "^2.15"
```

This is needed for Google Wallet integration. If your local version doesn't have this, you should use the git version.

## Complete Safe Pull Command

Run this sequence:

```bash
# Backup
cp composer.json composer.json.backup
cp .env .env.backup

# Stash
git stash save "Pre-pull backup $(date +%Y%m%d_%H%M%S)"

# Pull
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## If You Get Merge Conflicts

If `git stash pop` creates conflicts:

```bash
# 1. See the conflict
git status

# 2. Edit composer.json to resolve conflicts
#    Look for <<<<<<< markers

# 3. After resolving, mark as resolved
git add composer.json

# 4. Continue
git stash drop  # Remove the stash
```
