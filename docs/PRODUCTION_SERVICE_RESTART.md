# Production Service Restart Guide

## Finding the Correct PHP-FPM Service Name

The service name varies by PHP version and system. Try these commands to find yours:

### 1. Check Available PHP-FPM Services

```bash
# List all PHP-FPM services
systemctl list-units | grep php
# OR
systemctl list-units | grep fpm

# Check what's running
systemctl status php*-fpm*
```

### 2. Common Service Names

Try these (replace X.X with your PHP version):

```bash
# PHP 8.2
sudo systemctl restart php8.2-fpm

# PHP 8.3
sudo systemctl restart php8.3-fpm

# PHP 8.1
sudo systemctl restart php8.1-fpm

# Generic (some systems)
sudo systemctl restart php-fpm
sudo service php-fpm restart
```

### 3. Find Your PHP Version

```bash
php -v
```

This will show your PHP version (e.g., PHP 8.2.15), then use that version in the service name.

### 4. Alternative: Restart Web Server Instead

If you can't find php-fpm, restarting your web server (Nginx/Apache) will also reload PHP:

**For Nginx:**
```bash
sudo systemctl restart nginx
# OR
sudo service nginx restart
```

**For Apache:**
```bash
sudo systemctl restart apache2
# OR (on some systems)
sudo systemctl restart httpd
# OR
sudo service apache2 restart
```

### 5. Check if PHP-FPM is Even Needed

If you're using mod_php (Apache) or PHP-CGI, you don't need to restart php-fpm. Just restart the web server.

### 6. For Laravel Specifically

After config changes, you might not need to restart anything if you're using:
- **PHP-FPM with opcache disabled** - Config changes take effect immediately
- **PHP-FPM with opcache enabled** - You need to restart php-fpm OR clear opcache

**Clear opcache instead:**
```bash
# Create a route to clear opcache (temporary)
# Or restart php-fpm if you find the service name
```

## Complete Deployment Without PHP-FPM Restart

If you can't restart php-fpm, you can still deploy successfully:

```bash
# 1. Pull code
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Clear and cache config (THIS IS THE CRITICAL STEP)
php artisan config:clear
php artisan config:cache

# 5. Cache routes and views
php artisan route:cache
php artisan view:cache

# 6. Restart web server (this will reload PHP)
sudo systemctl restart nginx
# OR
sudo systemctl restart apache2

# 7. Restart queue workers (if using)
sudo supervisorctl restart kawhe-queue-worker:*
# OR
sudo systemctl restart kawhe-queue-worker
```

## Verify It Worked

After deployment, check:

```bash
# Verify config is cached
php artisan tinker
>>> config('cashier.price_id')
# Should return your STRIPE_PRICE_ID

# Check application is running
curl https://yourdomain.com/up
```

## Troubleshooting

### If config changes don't take effect:

1. **Clear all caches:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

2. **Restart web server** (this reloads PHP):
   ```bash
   sudo systemctl restart nginx
   ```

3. **If using opcache**, you might need to find and restart php-fpm:
   ```bash
   # Find the service
   systemctl list-units | grep php
   
   # Restart it (example for PHP 8.2)
   sudo systemctl restart php8.2-fpm
   ```

## Quick Reference

**Most important commands for this deployment:**
```bash
php artisan config:clear
php artisan config:cache
sudo systemctl restart nginx  # or apache2
```

The web server restart is usually sufficient to reload PHP and pick up config changes.
