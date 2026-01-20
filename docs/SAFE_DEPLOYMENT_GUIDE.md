# Safe Deployment Guide - Git Pull on Live Server

## ⚠️ Important: Files Changed via SFTP

If you've modified files on your live server via SFTP, pulling from git **WILL overwrite** those changes unless you handle them properly.

## 🔴 Files That Will Be Overwritten (Risky)

These files in git **will replace** your SFTP changes:
- All PHP files (`app/`, `routes/`, `config/`, etc.)
- Blade templates (`resources/views/`)
- JavaScript/CSS (`resources/js/`, `resources/css/`)
- Configuration files (`config/*.php`)
- Any tracked files in git

## ✅ Files That Are Safe (Ignored by Git)

These files are in `.gitignore` and **won't be affected**:
- `.env` (environment configuration)
- `storage/app/public/*` (user uploads)
- `storage/logs/*` (log files)
- `storage/framework/cache/*` (cache files)
- `storage/framework/sessions/*` (session files)
- `storage/framework/views/*` (compiled views)
- `bootstrap/cache/*` (bootstrap cache)
- `vendor/` (composer packages)
- `node_modules/` (npm packages)

## 🛡️ Safe Deployment Strategy

### Option 1: Backup & Merge (Recommended)

**Step 1: Backup Your Changes**
```bash
# SSH into your live server
cd /var/www/kawhe  # or your app path

# Create backup directory
mkdir -p ~/backups/$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=~/backups/$(date +%Y%m%d_%H%M%S)

# Backup modified files (if you know which ones)
# Example: if you modified config/app.php
cp config/app.php $BACKUP_DIR/

# Or backup entire app (safer but larger)
# tar -czf $BACKUP_DIR/app-backup.tar.gz .
```

**Step 2: Check What Will Change**
```bash
# See what git will change
git fetch origin
git diff HEAD origin/main --name-only

# See actual differences
git diff HEAD origin/main config/app.php  # if you modified this
```

**Step 3: Stash or Commit Local Changes**
```bash
# Option A: Stash your changes (temporary)
git stash save "Local SFTP changes before pull"

# Option B: Commit your changes to a branch
git checkout -b local-changes-backup
git add .
git commit -m "Backup of local SFTP changes"
git checkout main  # or your main branch
```

**Step 4: Pull from Git**
```bash
git pull origin main  # or your branch name
```

**Step 5: Restore Your Changes (if needed)**
```bash
# If you stashed:
git stash list  # see your stashes
git stash show -p stash@{0}  # preview changes
git stash pop  # restore changes (may have conflicts)

# If you committed to a branch:
git checkout local-changes-backup
# Review what you changed, then manually merge important parts
```

### Option 2: Selective File Protection

**Protect specific files from being overwritten:**

```bash
# Before pulling, make files read-only or move them
cd /var/www/kawhe

# Example: Protect a modified config file
cp config/app.php config/app.php.local
git pull origin main
# Compare and merge manually
diff config/app.php config/app.php.local
```

### Option 3: Use Git's Skip Worktree (Advanced)

**Tell git to ignore changes to specific files:**

```bash
# Mark file to skip during pulls
git update-index --skip-worktree config/app.php

# Now git pull won't overwrite this file
git pull origin main

# To undo later:
git update-index --no-skip-worktree config/app.php
```

## 📋 Pre-Deployment Checklist

Before running `git pull`, check:

```bash
# 1. Check current git status
git status

# 2. See what files you've modified locally
git diff --name-only

# 3. See what will change from remote
git fetch origin
git diff HEAD origin/main --name-only

# 4. Backup .env (always!)
cp .env .env.backup.$(date +%Y%m%d)

# 5. Check for uncommitted changes
git diff --stat
```

## 🚀 Safe Deployment Script

Create this script on your server: `deploy-safe.sh`

```bash
#!/bin/bash
set -e  # Exit on error

APP_DIR="/var/www/kawhe"
BACKUP_DIR="$HOME/backups/$(date +%Y%m%d_%H%M%S)"

echo "🚀 Starting safe deployment..."

# 1. Navigate to app directory
cd $APP_DIR

# 2. Create backup
echo "📦 Creating backup..."
mkdir -p $BACKUP_DIR
cp .env $BACKUP_DIR/.env.backup
tar -czf $BACKUP_DIR/app-backup.tar.gz --exclude='vendor' --exclude='node_modules' --exclude='.git' .

# 3. Check git status
echo "🔍 Checking git status..."
git status

# 4. Stash local changes
echo "💾 Stashing local changes..."
git stash save "Pre-deployment stash $(date +%Y%m%d_%H%M%S)"

# 5. Fetch latest
echo "⬇️  Fetching latest from git..."
git fetch origin

# 6. Show what will change
echo "📋 Files that will change:"
git diff HEAD origin/main --name-only

# 7. Ask for confirmation
read -p "Continue with deployment? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Deployment cancelled"
    git stash pop  # Restore stashed changes
    exit 1
fi

# 8. Pull changes
echo "⬇️  Pulling changes..."
git pull origin main

# 9. Install/update dependencies
echo "📦 Updating dependencies..."
composer install --no-dev --optimize-autoloader

# 10. Run migrations
echo "🗄️  Running migrations..."
php artisan migrate --force

# 11. Clear and cache config
echo "🧹 Clearing cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 12. Cache config for production
echo "⚡ Caching for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 13. Set permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 14. Restore stashed changes (if any)
echo "🔄 Checking for stashed changes..."
if git stash list | grep -q "Pre-deployment stash"; then
    echo "⚠️  You have stashed changes. Review with: git stash show -p"
    echo "   To restore: git stash pop"
fi

echo "✅ Deployment complete!"
echo "📦 Backup saved to: $BACKUP_DIR"
```

**Make it executable:**
```bash
chmod +x deploy-safe.sh
```

**Run it:**
```bash
./deploy-safe.sh
```

## 🔧 Post-Deployment Steps

After pulling, always run:

```bash
# 1. Install/update dependencies
composer install --no-dev --optimize-autoloader

# 2. Run migrations (if any)
php artisan migrate --force

# 3. Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 4. Rebuild caches (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 6. Restart services (if needed)
# For PHP-FPM:
sudo systemctl restart php8.2-fpm  # adjust version
# For Nginx:
sudo systemctl restart nginx
```

## ⚠️ Common Issues & Solutions

### Issue 1: `.env` File Overwritten
**Solution:** `.env` is in `.gitignore`, so it won't be overwritten. But always backup:
```bash
cp .env .env.backup
```

### Issue 2: Config Files Modified
**Solution:** Use environment variables instead of modifying `config/*.php` files:
```php
// In config/app.php, use:
'key' => env('APP_KEY'),

// Then set in .env:
APP_KEY=your-key-here
```

### Issue 3: Uploaded Files Lost
**Solution:** `storage/app/public` is in `.gitignore`, so uploads are safe. But ensure:
```bash
# Check symlink exists
ls -la public/storage

# If missing, create it:
php artisan storage:link
```

### Issue 4: Database Changes
**Solution:** Always run migrations after pulling:
```bash
php artisan migrate --force
```

## 🎯 Best Practices

1. **Never modify tracked files via SFTP** - Use environment variables or git
2. **Always backup before pulling** - Especially `.env` and database
3. **Test in staging first** - If possible, test deployments on staging server
4. **Use deployment scripts** - Automate the process to reduce errors
5. **Monitor after deployment** - Check logs and functionality
6. **Keep git and server in sync** - Commit production changes back to git when appropriate

## 📝 Quick Reference

```bash
# Safe pull (with backup)
cp .env .env.backup && git stash && git pull origin main && git stash pop

# Check what will change
git fetch origin && git diff HEAD origin/main --name-only

# Full safe deployment
./deploy-safe.sh
```

## 🆘 Emergency Rollback

If something breaks after deployment:

```bash
# 1. Restore from backup
cd /var/www/kawhe
tar -xzf ~/backups/LATEST_BACKUP/app-backup.tar.gz

# 2. Restore .env
cp ~/backups/LATEST_BACKUP/.env.backup .env

# 3. Clear caches
php artisan config:clear
php artisan cache:clear

# 4. Restart services
sudo systemctl restart php8.2-fpm nginx
```
