# Production Testing Guide

## Pre-Testing Setup

### 1. Verify Production Environment

```bash
# Check environment
php artisan about

# Verify config
php artisan config:show app.env
php artisan config:show app.debug

# Should show:
# app.env: production
# app.debug: false
```

### 2. Check Queue Worker

```bash
# Check if running
ps aux | grep queue:work

# If not running, start it
php artisan queue:work --daemon &

# Or check supervisor
sudo supervisorctl status
```

### 3. Verify SSL Certificate

```bash
# Check certificate
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com

# Or use online tool: https://www.ssllabs.com/ssltest/
```

## Complete Test Flow

### Test 1: User Registration & Login

1. **Register New User:**
   - Go to: `https://yourdomain.com/register`
   - Create account
   - Verify email sent (check queue/jobs)

2. **Login:**
   - Go to: `https://yourdomain.com/login`
   - Login with credentials
   - Verify dashboard loads

**Expected:** ✅ User created, email sent, login works

### Test 2: Store Creation

1. **Create Store:**
   - After login, create store
   - Fill in name, colors, logo
   - Save

2. **Verify Store:**
   - Check dashboard shows store
   - Check QR code is generated
   - Check store appears in scanner

**Expected:** ✅ Store created, QR code works

### Test 3: Customer Card Creation

1. **Join as Customer:**
   - Scan store QR code (or use join URL)
   - Enter customer name
   - Create card

2. **Verify Card:**
   - Card page loads
   - Shows correct store info
   - QR code visible
   - "Add to Apple Wallet" button works

**Expected:** ✅ Card created, page loads correctly

### Test 4: Apple Wallet Integration

1. **Add to Wallet:**
   - Click "Add to Apple Wallet"
   - Pass downloads and adds
   - Verify pass in Wallet app

2. **Verify Registration:**
   ```bash
   php artisan wallet:check-registration
   ```
   Should show device registered

3. **Verify Pass Details:**
   - Open pass in Wallet
   - Check stamp count (should be 0)
   - Check store branding
   - Check QR code

**Expected:** ✅ Pass adds, device registers, pass looks correct

### Test 5: Stamping Flow

1. **Stamp via Scanner:**
   - Go to: `https://yourdomain.com/scanner`
   - Login as merchant
   - Scan QR code from Wallet
   - Verify stamp count increments

2. **Monitor Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i "push\|stamp\|wallet"
   ```

3. **Verify Wallet Updates:**
   - Keep Wallet app open
   - Pass should update automatically within 1-2 seconds
   - Stamp count should increment

**Expected:** ✅ Stamp works, push sent, Wallet updates automatically

### Test 6: Reward Earning

1. **Earn Reward:**
   - Stamp until reward target reached
   - Monitor logs for reward earned

2. **Verify QR Code Changes:**
   - Check Wallet pass
   - QR code should change to `LR:{redeem_token}`
   - Pass should show reward available

**Expected:** ✅ Reward earned, QR code changes, pass updates

### Test 7: Reward Redemption

1. **Redeem Reward:**
   - Scan redeem QR code with scanner
   - Or redeem via merchant interface

2. **Verify:**
   - Reward balance decreases
   - QR code changes back to `LA:{public_token}` (if no more rewards)
   - Pass updates automatically

**Expected:** ✅ Redemption works, QR code updates, pass syncs

### Test 8: Billing/Subscription

1. **Create Subscription:**
   - Go to billing page
   - Click "Upgrade to Pro"
   - Complete Stripe checkout

2. **Verify:**
   - Subscription synced
   - Usage limits updated
   - Can create more than 50 cards

**Expected:** ✅ Subscription works, limits updated

### Test 9: Queue Processing

1. **Check Queue:**
   ```bash
   php artisan tinker
   ```
   ```php
   echo "Pending: " . \DB::table('jobs')->count() . "\n";
   echo "Failed: " . \DB::table('failed_jobs')->count() . "\n";
   exit
   ```

2. **Process Jobs:**
   ```bash
   php artisan queue:work --stop-when-empty
   ```

**Expected:** ✅ Jobs process, no failures

### Test 10: Error Handling

1. **Test Invalid QR:**
   - Scan invalid QR code
   - Should show error message (not 500)

2. **Test Invalid Token:**
   - Try invalid redeem token
   - Should show error (not 500)

3. **Check Logs:**
   ```bash
   tail -n 100 storage/logs/laravel.log | grep -i "error\|exception"
   ```

**Expected:** ✅ Errors handled gracefully, logged properly

## Performance Testing

### Load Test Critical Endpoints

```bash
# Test homepage
ab -n 100 -c 10 https://yourdomain.com/

# Test scanner endpoint
ab -n 50 -c 5 https://yourdomain.com/scanner

# Test card page
ab -n 50 -c 5 https://yourdomain.com/c/{public_token}
```

**Expected:** ✅ Response times < 500ms, no errors

### Database Performance

```bash
# Check slow queries
# Enable slow query log in MySQL
# Check: /var/log/mysql/slow-query.log
```

## Security Testing

### 1. HTTPS Enforcement

```bash
# Test HTTP redirect
curl -I http://yourdomain.com
# Should redirect to HTTPS
```

### 2. CSRF Protection

- Try submitting form without CSRF token
- Should be rejected

### 3. Authentication

- Try accessing protected routes without login
- Should redirect to login

### 4. Authorization

- Try accessing another merchant's store
- Should be denied

## Monitoring Checklist

### Daily Checks

- [ ] Check error logs: `tail -n 50 storage/logs/laravel.log | grep ERROR`
- [ ] Check queue status: `php artisan queue:monitor`
- [ ] Check failed jobs: `php artisan queue:failed`
- [ ] Check disk space: `df -h`
- [ ] Check server resources: `htop` or `top`

### Weekly Checks

- [ ] Review error logs for patterns
- [ ] Check database size and growth
- [ ] Review slow queries
- [ ] Check SSL certificate expiration
- [ ] Review backup success

## Automated Testing Script

Save as `test-production.sh`:

```bash
#!/bin/bash

DOMAIN="https://yourdomain.com"
echo "=== Production Smoke Tests ==="
echo ""

# Test 1: Homepage
echo "1. Testing homepage..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $DOMAIN)
if [ "$STATUS" = "200" ]; then
    echo "   ✅ Homepage loads"
else
    echo "   ❌ Homepage failed: $STATUS"
fi

# Test 2: Login page
echo "2. Testing login page..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $DOMAIN/login)
if [ "$STATUS" = "200" ]; then
    echo "   ✅ Login page loads"
else
    echo "   ❌ Login page failed: $STATUS"
fi

# Test 3: Queue status
echo "3. Checking queue..."
PENDING=$(php artisan tinker --execute="echo \DB::table('jobs')->count();" 2>/dev/null | tail -1)
FAILED=$(php artisan tinker --execute="echo \DB::table('failed_jobs')->count();" 2>/dev/null | tail -1)
echo "   Pending jobs: $PENDING"
echo "   Failed jobs: $FAILED"

# Test 4: Database connection
echo "4. Testing database..."
php artisan tinker --execute="echo \DB::connection()->getPdo() ? '✅ Connected' : '❌ Failed';" 2>/dev/null | tail -1

# Test 5: Config cache
echo "5. Checking config..."
if php artisan config:show app.env 2>/dev/null | grep -q "production"; then
    echo "   ✅ Config cached"
else
    echo "   ❌ Config not cached"
fi

# Test 6: Queue worker
echo "6. Checking queue worker..."
if pgrep -f "queue:work" > /dev/null; then
    echo "   ✅ Queue worker running"
else
    echo "   ❌ Queue worker not running"
fi

echo ""
echo "=== Test Complete ==="
```

Make executable: `chmod +x test-production.sh`

## Post-Launch Monitoring

### First 24 Hours

- Monitor error logs every hour
- Check queue processing
- Watch for user registrations
- Monitor server resources
- Check Stripe webhooks

### First Week

- Daily error log review
- Monitor queue backlog
- Check database performance
- Review user feedback
- Monitor wallet pass updates

### Ongoing

- Weekly error log review
- Monthly performance review
- Quarterly security audit
- Regular backup verification

## Rollback Plan

If issues occur:

1. **Stop queue worker:**
   ```bash
   sudo supervisorctl stop kawhe-queue-worker:*
   ```

2. **Revert code:**
   ```bash
   git revert HEAD
   git push origin main
   git pull origin main
   ```

3. **Clear caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

4. **Restart services:**
   ```bash
   sudo systemctl restart php8.4-fpm
   sudo supervisorctl start kawhe-queue-worker:*
   ```

5. **Verify:**
   ```bash
   php artisan about
   curl -I https://yourdomain.com
   ```

## Success Criteria

✅ All smoke tests pass  
✅ No critical errors in logs  
✅ Queue processing normally  
✅ Wallet updates working  
✅ Emails sending  
✅ Stripe webhooks working  
✅ Response times acceptable  
✅ SSL certificate valid  
✅ Backups running  
✅ Monitoring active  
