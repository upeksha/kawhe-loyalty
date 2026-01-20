# Production Readiness Checklist

## 🔒 Security

### Environment Variables
- [ ] **All sensitive data in `.env`** (not in code)
- [ ] **`.env` file excluded from git** (check `.gitignore`)
- [ ] **`APP_KEY` is set** and unique
- [ ] **`APP_ENV=production`**
- [ ] **`APP_DEBUG=false`** (critical!)
- [ ] **Database credentials** are secure
- [ ] **Stripe keys** are production keys (not test keys)
- [ ] **APNs keys** are production (not sandbox)
- [ ] **SendGrid API key** is set
- [ ] **Queue connection** is configured (`database` or `redis`)

### SSL/HTTPS
- [ ] **SSL certificate** installed and valid
- [ ] **HTTPS enforced** (redirect HTTP to HTTPS)
- [ ] **HSTS headers** enabled
- [ ] **Certificate auto-renewal** configured (Let's Encrypt)

### Application Security
- [ ] **CSRF protection** enabled (Laravel default)
- [ ] **Rate limiting** on API endpoints
- [ ] **SQL injection** protection (using Eloquent/Query Builder)
- [ ] **XSS protection** (Blade auto-escaping)
- [ ] **File upload validation** (size, type, etc.)
- [ ] **Authentication** required for sensitive routes
- [ ] **Authorization** checks in place (merchant owns store, etc.)

## ⚙️ Configuration

### Laravel Config
- [ ] **Config cached**: `php artisan config:cache`
- [ ] **Routes cached**: `php artisan route:cache`
- [ ] **Views cached**: `php artisan view:cache`
- [ ] **Events cached**: `php artisan event:cache`
- [ ] **Optimize autoloader**: `composer install --optimize-autoloader --no-dev`

### Queue Configuration
- [ ] **Queue worker running** (supervisor/systemd)
- [ ] **Failed jobs table** monitored
- [ ] **Queue retry logic** configured
- [ ] **Job timeout** settings appropriate

### Database
- [ ] **Migrations run**: `php artisan migrate --force`
- [ ] **Database backups** configured
- [ ] **Connection pooling** if needed
- [ ] **Indexes** on frequently queried columns

## 📊 Monitoring & Logging

### Logging
- [ ] **Log rotation** configured
- [ ] **Log level** set to `production` (not `debug`)
- [ ] **Error tracking** (Sentry, Bugsnag, etc.) - optional but recommended
- [ ] **Log storage** has sufficient space

### Monitoring
- [ ] **Server monitoring** (CPU, memory, disk)
- [ ] **Application monitoring** (response times, errors)
- [ ] **Queue monitoring** (pending jobs, failed jobs)
- [ ] **Database monitoring** (slow queries, connections)
- [ ] **Uptime monitoring** (external service)

### Alerts
- [ ] **Error alerts** configured
- [ ] **Queue backlog alerts**
- [ ] **Disk space alerts**
- [ ] **SSL expiration alerts**

## 🚀 Performance

### Caching
- [ ] **Config cached**
- [ ] **Routes cached**
- [ ] **Views cached**
- [ ] **OPcache enabled** (PHP)
- [ ] **Redis/Memcached** for session/cache (if using)

### Assets
- [ ] **Assets minified** (CSS, JS)
- [ ] **Images optimized**
- [ ] **CDN configured** (optional but recommended)
- [ ] **Browser caching** headers set

### Database
- [ ] **Query optimization** (N+1 queries fixed)
- [ ] **Indexes** on foreign keys and frequently queried columns
- [ ] **Database connection pooling** if needed

## 🔄 Deployment

### Pre-Deployment
- [ ] **All tests pass** (if you have tests)
- [ ] **Code reviewed**
- [ ] **Dependencies updated**: `composer update`
- [ ] **Migrations tested** on staging
- [ ] **Backup database** before migration

### Deployment Process
- [ ] **Deployment script** tested
- [ ] **Zero-downtime deployment** strategy
- [ ] **Rollback plan** in place
- [ ] **Health checks** after deployment

### Post-Deployment
- [ ] **Verify application** is running
- [ ] **Check logs** for errors
- [ ] **Test critical flows** (signup, stamping, wallet)
- [ ] **Monitor queue** processing
- [ ] **Check SSL certificate**

## 🧪 Testing in Production

### Smoke Tests
- [ ] **Homepage loads**
- [ ] **User registration** works
- [ ] **User login** works
- [ ] **Store creation** works
- [ ] **Card creation** works
- [ ] **QR code scanning** works
- [ ] **Stamping** works
- [ ] **Wallet pass generation** works
- [ ] **Wallet pass download** works
- [ ] **Wallet auto-update** works (push notifications)
- [ ] **Reward redemption** works
- [ ] **Billing/subscription** works

### Integration Tests
- [ ] **Stripe webhooks** working
- [ ] **SendGrid emails** sending
- [ ] **APNs push notifications** working
- [ ] **Queue jobs** processing
- [ ] **Database transactions** working

## 📝 Documentation

- [ ] **Environment variables** documented
- [ ] **Deployment process** documented
- [ ] **Troubleshooting guide** available
- [ ] **API documentation** (if applicable)
- [ ] **Runbook** for common issues

## ✅ Quick Production Setup Script

Run this on your production server:

```bash
#!/bin/bash
cd /var/www/kawhe

# 1. Pull latest code
git pull origin main

# 2. Install dependencies
composer install --optimize-autoloader --no-dev

# 3. Run migrations
php artisan migrate --force

# 4. Clear and cache everything
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 5. Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Restart queue worker (if using supervisor)
sudo supervisorctl restart kawhe-queue-worker:*

# 7. Restart PHP-FPM
sudo systemctl restart php8.4-fpm
# OR
sudo service php8.4-fpm restart

# 8. Verify
php artisan about
```

## 🎯 Critical Production Settings

### .env File Must Have:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Security
APP_KEY=base64:... (must be set)

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_secure_password

# Queue
QUEUE_CONNECTION=database

# Mail (SendGrid)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

# Stripe (Production keys!)
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Apple Wallet (Production)
APPLE_APNS_USE_SANDBOX=false
APPLE_APNS_PRODUCTION=true

# Google Wallet
GOOGLE_WALLET_ISSUER_ID=your_issuer_id
GOOGLE_WALLET_REVIEW_STATUS=underReview  # or "approved"
```

## 🚨 Common Production Issues

### Issue: 500 Errors
**Check:**
- `APP_DEBUG=false` in `.env`
- Logs: `tail -f storage/logs/laravel.log`
- Permissions: `chmod -R 775 storage bootstrap/cache`
- Ownership: `chown -R www-data:www-data storage bootstrap/cache`

### Issue: Queue Not Processing
**Check:**
- Queue worker running: `ps aux | grep queue:work`
- Start worker: `php artisan queue:work --daemon`
- Or use supervisor/systemd

### Issue: Emails Not Sending
**Check:**
- SendGrid API key correct
- Queue worker processing jobs
- Check failed jobs: `php artisan queue:failed`

### Issue: Wallet Passes Not Updating
**Check:**
- APNs push enabled: `php artisan config:show wallet.apple.push_enabled`
- Queue worker running
- Check logs: `tail -f storage/logs/laravel.log | grep -i wallet`

## 📋 Pre-Launch Checklist

- [ ] All security items checked
- [ ] All configuration items checked
- [ ] Monitoring set up
- [ ] Backups configured
- [ ] SSL certificate valid
- [ ] Queue worker running
- [ ] All smoke tests pass
- [ ] Documentation complete
- [ ] Team trained on deployment process
- [ ] Rollback plan ready
