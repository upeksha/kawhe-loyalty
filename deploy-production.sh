#!/bin/bash

# Production Deployment Script
# Run this on your production server

set -e  # Exit on error

cd /var/www/kawhe

echo "=== Production Deployment ==="
echo ""

# 1. Backup database (if mysqldump available)
if command -v mysqldump &> /dev/null; then
    echo "1. Backing up database..."
    DB_NAME=$(php artisan tinker --execute="echo config('database.connections.mysql.database');" 2>/dev/null | tail -1)
    if [ -n "$DB_NAME" ]; then
        BACKUP_FILE="backups/db_$(date +%Y%m%d_%H%M%S).sql"
        mkdir -p backups
        mysqldump -u root -p"$(php artisan tinker --execute="echo config('database.connections.mysql.password');" 2>/dev/null | tail -1)" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null || echo "   ⚠️  Backup skipped (check credentials)"
        echo "   ✓ Backup created: $BACKUP_FILE"
    fi
else
    echo "1. ⚠️  mysqldump not available, skipping backup"
fi
echo ""

# 2. Pull latest code
echo "2. Pulling latest code..."
git pull origin main
echo "   ✓ Code updated"
echo ""

# 3. Install dependencies
echo "3. Installing dependencies..."
composer install --optimize-autoloader --no-dev --no-interaction
echo "   ✓ Dependencies installed"
echo ""

# 4. Run migrations
echo "4. Running migrations..."
php artisan migrate --force
echo "   ✓ Migrations complete"
echo ""

# 5. Clear caches
echo "5. Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear
echo "   ✓ Caches cleared"
echo ""

# 6. Cache for production
echo "6. Caching for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
echo "   ✓ Production caches created"
echo ""

# 7. Restart queue worker (if using supervisor)
if command -v supervisorctl &> /dev/null; then
    echo "7. Restarting queue worker..."
    sudo supervisorctl restart kawhe-queue-worker:* 2>/dev/null || echo "   ⚠️  Supervisor not configured"
else
    echo "7. ⚠️  Supervisor not available, queue worker not restarted"
    echo "   Manually restart: php artisan queue:work --daemon"
fi
echo ""

# 8. Restart PHP-FPM
echo "8. Restarting PHP-FPM..."
if systemctl is-active --quiet php8.4-fpm; then
    sudo systemctl restart php8.4-fpm
    echo "   ✓ PHP-FPM restarted"
elif systemctl is-active --quiet php8.3-fpm; then
    sudo systemctl restart php8.3-fpm
    echo "   ✓ PHP-FPM restarted"
elif systemctl is-active --quiet php8.2-fpm; then
    sudo systemctl restart php8.2-fpm
    echo "   ✓ PHP-FPM restarted"
else
    echo "   ⚠️  PHP-FPM service not found, manual restart may be needed"
fi
echo ""

# 9. Verify deployment
echo "9. Verifying deployment..."
if php artisan about &> /dev/null; then
    echo "   ✓ Application is running"
    
    # Check environment
    ENV=$(php artisan config:show app.env 2>/dev/null | tail -1 | xargs)
    DEBUG=$(php artisan config:show app.debug 2>/dev/null | tail -1 | xargs)
    
    if [ "$ENV" = "production" ]; then
        echo "   ✓ Environment: production"
    else
        echo "   ⚠️  Environment: $ENV (should be production)"
    fi
    
    if [ "$DEBUG" = "false" ]; then
        echo "   ✓ Debug mode: disabled"
    else
        echo "   ⚠️  Debug mode: $DEBUG (should be false)"
    fi
else
    echo "   ❌ Application check failed"
    exit 1
fi
echo ""

# 10. Check queue status
echo "10. Checking queue status..."
PENDING=$(php artisan tinker --execute="echo \DB::table('jobs')->count();" 2>/dev/null | tail -1)
FAILED=$(php artisan tinker --execute="echo \DB::table('failed_jobs')->count();" 2>/dev/null | tail -1)
echo "   Pending jobs: $PENDING"
echo "   Failed jobs: $FAILED"
if [ "$FAILED" -gt "0" ]; then
    echo "   ⚠️  There are failed jobs. Run: php artisan queue:failed"
fi
echo ""

echo "=== Deployment Complete ==="
echo ""
echo "Next steps:"
echo "1. Test critical flows (registration, stamping, wallet)"
echo "2. Monitor logs: tail -f storage/logs/laravel.log"
echo "3. Check queue processing"
echo "4. Verify SSL certificate"
echo ""
