#!/bin/bash

# Fix Permissions Script
# Run this on your server: bash FIX_PERMISSIONS.sh

echo "=== Fixing Laravel Permissions ==="
echo ""

cd /var/www/kawhe || exit 1

echo "1. Setting ownership to www-data..."
chown -R www-data:www-data storage bootstrap/cache

echo "2. Setting directory permissions..."
find storage -type d -exec chmod 775 {} \;
find bootstrap/cache -type d -exec chmod 775 {} \;

echo "3. Setting file permissions..."
find storage -type f -exec chmod 664 {} \;
find bootstrap/cache -type f -exec chmod 664 {} \;

echo "4. Making sure specific directories are writable..."
chmod -R 775 storage/framework
chmod -R 775 storage/logs
chmod -R 775 storage/app
chmod -R 775 bootstrap/cache

echo "5. Verifying permissions..."
ls -la storage/framework/views/ | head -5
ls -la bootstrap/cache/ | head -5

echo ""
echo "=== Permissions Fixed ==="
echo ""
echo "Now try accessing your site again."
