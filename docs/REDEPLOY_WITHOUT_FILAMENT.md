# Redeploy Without Filament

Your **local** app is already reverted to the pre-Filament commit (`c6ec0cc`). To redeploy this version (no Filament) to your server(s), do the following.

---

## 1. Push the reverted code to GitHub (one-time)

Your local `main` is at `c6ec0cc` and is behind `origin/main`. To make the server deploy this version, update the remote to match:

```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
git push --force-with-lease origin main
```

**Warning:** This rewrites `origin/main` to the pre-Filament commit and drops the 8 Filament-related commits. Anyone else with the repo should run `git fetch origin && git reset --hard origin/main` (and not merge old main back).

---

## 2. Deploy on the server (testing first, then production)

Run these on the server **as the user that owns the app** (e.g. `www-data` or your deploy user). Do **testing** first, verify, then do **production**.

**If you run as root** and see `fatal: detected dubious ownership in repository`:
- Git will refuse to run; the deploy will not update code. Fix once per server:
  ```bash
  git config --global --add safe.directory /var/www/kawhe-testing   # on testing
  git config --global --add safe.directory /var/www/kawhe            # on production
  ```
- Then re-run the deploy steps (fetch, reset, etc.).

**`.env` paths**: Use paths that match **this** server. On **testing** use `/var/www/kawhe-testing/...` (or relative paths). On **production** use `/var/www/kawhe/...`. Do not copy production `.env` to testing without changing absolute paths; otherwise Apple/Google Wallet and APNS will point at the wrong app root.

### 2.1 Testing: `/var/www/kawhe-testing` (testing.kawhe.shop)

```bash
cd /var/www/kawhe-testing
git fetch origin
git reset --hard origin/main
composer install --no-interaction --no-scripts
rm -f bootstrap/cache/config.php bootstrap/cache/services.php bootstrap/cache/packages.php
php artisan package:discover --ansi
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
rm -rf app/Filament
```

### 2.2 Production: `/var/www/kawhe` (app.kawhe.shop)

After testing works, repeat with the production path:

```bash
cd /var/www/kawhe
git fetch origin
git reset --hard origin/main
composer install --no-interaction --no-scripts
rm -f bootstrap/cache/config.php bootstrap/cache/services.php bootstrap/cache/packages.php
php artisan package:discover --ansi
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
rm -rf app/Filament
```

### 2.3 Restart PHP-FPM (if needed)

```bash
sudo systemctl reload php8.2-fpm
# or the PHP version your server uses, e.g. php8.1-fpm
```

---

## 3. Quick checks after deploy

On the server:

```bash
cd /var/www/kawhe-testing   # or /var/www/kawhe
composer show | grep -i filament
# (no output = OK)
php artisan route:list | grep -i filament
# (no output = OK)
php artisan about
# (runs without error = OK)
```

Then in the browser: log in and open `/dashboard`, `/merchant/dashboard`, `/merchant/stores` — they should work without Filament (blade/controllers only).

---

## Summary

| Step | Where | Action |
|------|--------|--------|
| 1 | Local (Mac) | `git push --force-with-lease origin main` |
| 2a | Server testing | `cd /var/www/kawhe-testing`, fetch + reset + composer + artisan + rm app/Filament |
| 2b | Server production | Same in `/var/www/kawhe` after testing is OK |
| 3 | Server | Reload PHP-FPM; run checks above |

After this, both testing and production will be running the reverted app **without Filament**.
