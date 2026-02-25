# Revert Filament – Complete Runbook

Restore the Laravel app to the **exact state before Filament** was introduced. No Filament routes (`/admin`, `/merchant` panels), providers, config, or packages.

---

## Context from this repo

- **First Filament commit:** `79ab985` — "Add Filament panels (Option C): Admin + Merchant with scanner reusing backend"
- **Pre-Filament commit (last known good):** `c6ec0cc` — "Google Wallet Generic pass: show customer name only, remove 'Hi'"
- **Later commits** (1609b3c, 62d8e55, ce8529a, 19cb9d7, 843f181, 6d93633) added more Filament/merchant panel work and are mixed with non-Filament changes.
- **Existing non-Filament routes:** `admin.dashboard` and `merchant.dashboard` are already defined in `routes/web.php` (closure and `AdminDashboardController`). After Filament is removed, these same route names will still resolve to those web routes.

---

# A) Plan A: Git revert (recommended only if you can lose later work)

Use this **only if** you are okay returning to the exact state at `c6ec0cc` and **discarding all commits after it** (including non-Filament work).

### A.1 Find the pre-Filament commit (optional check)

```bash
cd /path/to/kawhe  # local: your repo; server: /var/www/kawhe-testing or /var/www/kawhe
git log --oneline | grep -i filament
```

First Filament-related commit in this repo: **79ab985**. The commit **before** it is **c6ec0cc** (pre-Filament).

### A.2 Backup and revert (destructive)

**LOCAL (Mac):**

```bash
cd /path/to/kawhe
git status
git stash push -u -m "pre-revert backup"   # if you have uncommitted changes
git branch backup-before-filament-revert   # create backup branch at current HEAD
git reset --hard c6ec0cc
```

**SERVER (Ubuntu) – do testing first, then production:**

```bash
# 1) Testing
cd /var/www/kawhe-testing
sudo -u www-data git fetch origin
sudo -u www-data git branch backup-before-filament-revert
sudo -u www-data git reset --hard c6ec0cc

# 2) After verification on testing, repeat for production
cd /var/www/kawhe
sudo -u www-data git fetch origin
sudo -u www-data git branch backup-before-filament-revert
sudo -u www-data git reset --hard c6ec0cc
```

### A.3 Composer and caches (after reset)

```bash
composer install --no-dev
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### A.4 Verification (Plan A)

- `composer show | grep -i filament` → no output
- `php artisan route:list | grep -i filament` → no output
- `php artisan route:list | grep -E "admin|merchant"` → only web.php routes (e.g. `GET|HEAD admin/dashboard`, `GET|HEAD merchant/dashboard`, `merchant/stores`, etc.)
- `php artisan about` → runs without error
- Visit `/dashboard` → redirects to `/merchant/dashboard` or `/admin/dashboard` (blade/controller, not Filament)

---

# B) Plan B: Manual removal (keep all non-Filament commits)

Use this when you want to **remove only Filament** and keep all other work since `79ab985`.

## B.1 Backup and branch

**LOCAL (Mac):**

```bash
cd /path/to/kawhe
git status
git stash push -u -m "pre-filament-removal backup"   # if uncommitted changes
git checkout -b remove-filament
```

**SERVER (Ubuntu):** Do manual removal in a clone or after pulling a branch that you’ve prepared locally. Prefer: do edits and composer on **local**, commit, then deploy to server (e.g. pull on server). If you must do it on server:

```bash
cd /var/www/kawhe-testing
sudo -u www-data git fetch origin
sudo -u www-data git checkout -b remove-filament
```

## B.2 Remove Filament code and config

### Step 1 – Delete Filament app code and providers

**LOCAL and SERVER (same):**

```bash
rm -rf app/Filament
rm -rf app/Providers/Filament
```

### Step 2 – Remove Filament from bootstrap/providers.php

Edit `bootstrap/providers.php`. Remove the two Filament lines so it looks like:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
];
```

### Step 3 – Remove Filament from Composer

Edit `composer.json`:

1. **Remove the package** from `require`:
   - Delete: `"filament/filament": "^4.0",`

2. **Remove Filament from scripts** (so `composer install` doesn’t run Filament commands):
   - In `"post-autoload-dump"` change:
     - From: `"@php artisan package:discover --ansi","@php artisan filament:upgrade"`
     - To: `"@php artisan package:discover --ansi"`
   - Remove any other `filament:*` or `filament:upgrade` entries if present.

Then run:

```bash
composer update filament/filament --with-all-dependencies
# Or, to remove and leave rest of lockfile as-is:
composer remove filament/filament
```

If `composer remove` pulls in unwanted changes, use:

```bash
composer update --lock
composer install
```

### Step 4 – Remove published Filament assets

```bash
rm -rf public/css/filament
rm -rf public/js/filament
rm -rf public/fonts/filament
```

### Step 5 – Remove Filament config (if present)

```bash
rm -f config/filament.php
rm -f config/filament-*.php
ls config/
```

If you see no `filament*.php`, nothing else to delete.

### Step 6 – User model (optional cleanup)

If `app/Models/User.php` implements `FilamentUser` or uses Filament-specific imports, remove them:

```bash
grep -n FilamentUser app/Models/User.php
```

Remove `implements FilamentUser` and any `use Filament\...` related to Filament. The runbook assumes your `User` model has no other Filament requirements beyond that.

### Step 7 – Clear caches and regenerate autoload

**LOCAL (Mac) and SERVER (Ubuntu):**

```bash
composer dump-autoload
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## B.3 Verification (Plan B)

Run these in the project root (local or server).

1. **No Filament packages**
   ```bash
   composer show | grep -i filament
   ```
   Expected: no output.

2. **No Filament routes**
   ```bash
   php artisan route:list | grep -i filament
   ```
   Expected: no output.

3. **Only web admin/merchant routes**
   ```bash
   php artisan route:list | grep -E "/admin|/merchant"
   ```
   Expected: only routes from `web.php` (e.g. `admin/dashboard`, `merchant/dashboard`, `merchant/stores`, etc.), no Filament panel routes.

4. **No Filament providers**
   ```bash
   grep -i filament bootstrap/providers.php
   ```
   Expected: no output.

5. **App boots**
   ```bash
   php artisan about
   php artisan config:cache
   ```
   Expected: no errors.

6. **No Filament assets**
   ```bash
   ls public/css/filament 2>/dev/null || echo "OK: no public/css/filament"
   ls public/js/filament 2>/dev/null || echo "OK: no public/js/filament"
   ls public/fonts/filament 2>/dev/null || echo "OK: no public/fonts/filament"
   ```

7. **Dashboard redirects**
   - Log in as merchant → `/dashboard` should redirect to `/merchant/dashboard` (blade/usage stats).
   - Log in as super admin → `/dashboard` should redirect to `/admin/dashboard` (AdminDashboardController).

---

# C) Rollback / safety plan

## Before you start

- **Backup branch:**  
  `git branch backup-before-filament-revert`
- **Backup DB** (server):  
  e.g. `mysqldump` or your normal backup for `/var/www/kawhe-testing` and `/var/www/kawhe`.
- **Uncommitted changes:**  
  `git stash push -u -m "pre-revert"`

## If something goes wrong

**Plan A (reset):**

```bash
git checkout main
git reset --hard backup-before-filament-revert
# or
git reset --hard origin/main
composer install
php artisan optimize:clear && php artisan config:cache && php artisan route:cache
```

**Plan B (manual removal):**

- Restore from backup branch or revert the “remove-filament” commit.
- Run `composer install` and `php artisan optimize:clear`, `config:cache`, `route:cache`, `view:cache`.

## Server order

1. Do **testing** first: `/var/www/kawhe-testing` (testing.kawhe.shop).
2. Verify login, `/dashboard`, `/merchant/dashboard`, `/merchant/stores`, `/admin/dashboard`.
3. Then repeat the same steps on **production**: `/var/www/kawhe` (app.kawhe.shop).

---

# D) Final verification checklist

Use this after either plan.

- [ ] `composer show | grep -i filament` → no output
- [ ] `php artisan route:list | grep -i filament` → no output
- [ ] `php artisan route:list | grep -E "/admin|/merchant"` → only web routes
- [ ] `grep -i filament bootstrap/providers.php` → no output
- [ ] No `app/Filament`, no `app/Providers/Filament`
- [ ] No `public/css/filament`, `public/js/filament`, `public/fonts/filament`
- [ ] No `config/filament.php` (or any `config/filament-*.php`)
- [ ] `php artisan about` runs without error
- [ ] `php artisan config:cache` and `php artisan route:cache` run without error
- [ ] Merchant login → `/dashboard` → redirect to `/merchant/dashboard` (blade)
- [ ] Super admin login → `/dashboard` → redirect to `/admin/dashboard` (controller)
- [ ] `/merchant/stores` and store edit work (StoreController / blade views)

---

## Quick reference – commands by environment

### LOCAL (Mac)

```bash
# Plan A
git branch backup-before-filament-revert
git reset --hard c6ec0cc
composer install
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache

# Plan B
git checkout -b remove-filament
rm -rf app/Filament app/Providers/Filament
# Edit bootstrap/providers.php and composer.json (see B.2)
composer remove filament/filament
rm -rf public/css/filament public/js/filament public/fonts/filament
composer dump-autoload
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### SERVER (Ubuntu – testing then production)

```bash
cd /var/www/kawhe-testing   # or /var/www/kawhe for production
sudo -u www-data git fetch origin
# Then either Plan A (reset to c6ec0cc) or pull your Plan B branch and run composer + artisan commands
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
# Restart PHP-FPM if needed: sudo systemctl reload php8.2-fpm
```

After completion, confirm with section **D) Final verification checklist** and that the app runs normally without any Filament routes or assets.
