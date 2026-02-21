# Email Verification Test Checklist

This document provides step-by-step test procedures to verify email verification works correctly in both local and production environments.

## Prerequisites

- Application deployed and accessible
- Database configured and migrated
- Queue worker running (for production)
- SendGrid account configured (for production)

## Test 1: Local Test with Log Driver

**Purpose:** Verify email queuing works without external dependencies.

### Steps:

1. **Configure environment:**
   ```bash
   # In .env
   MAIL_MAILER=log
   QUEUE_CONNECTION=database
   ```

2. **Start queue worker:**
   ```bash
   php artisan queue:work
   ```

3. **Create a customer:**
   - Navigate to a store join link: `/join/{slug}?t={token}`
   - Enter name and email
   - Submit form
   - **Expected:** Customer created, redirected to card page

4. **Request verification email:**
   - On card page, click "Verify Email" button
   - **Expected:** Success message "Verification email sent! Please check your inbox."

5. **Check queue:**
   ```bash
   # In queue worker terminal, you should see:
   # Processing: App\Mail\VerifyCustomerEmail
   ```

6. **Check log file:**
   ```bash
   tail -f storage/logs/laravel.log
   # Should see email content logged
   ```

7. **Extract verification link:**
   - Find the verification URL in the log file
   - Copy the full URL

8. **Verify email:**
   - Open the verification URL in browser
   - **Expected:** Redirected to card page with "Email verified successfully!" message

9. **Test redemption:**
   - Earn enough stamps to unlock a reward
   - Try to redeem
   - **Expected:** Redemption succeeds (email is verified)

10. **Test unverified redemption:**
    - Create a new customer (different email)
    - Earn stamps to unlock reward
    - Try to redeem without verifying email
    - **Expected:** Error message "You must verify your email address before you can redeem rewards"

### Success Criteria:
- ✅ Customer creation never fails due to email issues
- ✅ Verification email is queued successfully
- ✅ Verification link works and redirects correctly
- ✅ Redemption only works after email verification
- ✅ Unverified customers cannot redeem

---

## Test 2: Production Test with SendGrid (Normal Operation)

**Purpose:** Verify email sending works with SendGrid in production.

### Steps:

1. **Configure environment:**
   ```bash
   # In .env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.sendgrid.net
   MAIL_PORT=587
   MAIL_USERNAME=apikey
   MAIL_PASSWORD=your_sendgrid_api_key
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME="Kawhe Loyalty"
   QUEUE_CONNECTION=database
   APP_URL=https://app.kawhe.shop
   APP_ENV=production
   ```

2. **Ensure queue worker is running:**
   ```bash
   # Check status
   sudo supervisorctl status kawhe-queue-worker:*
   # OR
   sudo systemctl status kawhe-queue-worker
   
   # If not running, start it
   sudo supervisorctl start kawhe-queue-worker:*
   # OR
   sudo systemctl start kawhe-queue-worker
   ```

3. **Test email command:**
   ```bash
   php artisan kawhe:mail-test your-email@example.com
   # Expected: "✓ Email queued successfully!"
   ```

4. **Process queue:**
   ```bash
   php artisan queue:work --once
   # Expected: Email sent successfully
   ```

5. **Check email inbox:**
   - Open your email inbox
   - **Expected:** Test verification email received

6. **Create customer and verify:**
   - Follow steps from Test 1 (steps 3-9)
   - **Expected:** Real email received, verification link works

### Success Criteria:
- ✅ Test email command works
- ✅ Real verification emails are sent via SendGrid
- ✅ Verification links work correctly
- ✅ Emails arrive in inbox (not spam)

---

## Test 3: SendGrid Down / Credits Exceeded

**Purpose:** Verify application continues working when SendGrid is unavailable.

### Steps:

1. **Simulate SendGrid failure:**
   ```bash
   # Option A: Use invalid API key
   # In .env, set:
   MAIL_PASSWORD=invalid_key
   
   # Option B: Temporarily block SendGrid (if possible)
   # Or use SendGrid dashboard to disable account
   ```

2. **Clear config cache:**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

3. **Create customer:**
   - Navigate to join link
   - Enter details and submit
   - **Expected:** Customer created successfully (no error)

4. **Request verification email:**
   - Click "Verify Email" button
   - **Expected:** Success message (email queued, not sent yet)

5. **Check queue:**
   ```bash
   php artisan queue:work --once
   # Expected: Job fails, logged to failed_jobs table
   ```

6. **Check failed jobs:**
   ```bash
   php artisan queue:failed
   # Expected: Shows failed job with error message
   ```

7. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i mail
   # Expected: Error logged about SendGrid failure
   ```

8. **Verify customer can still use card:**
   - Try to stamp the card
   - **Expected:** Stamping works normally

9. **Try to redeem (unverified):**
   - Earn stamps to unlock reward
   - Try to redeem
   - **Expected:** Error "You must verify your email address" (expected behavior)

10. **Fix SendGrid and retry:**
    ```bash
    # Restore valid API key in .env
    php artisan config:clear
    php artisan config:cache
    
    # Retry failed job
    php artisan queue:retry all
    
    # Process queue
    php artisan queue:work --once
    # Expected: Email sent successfully
    ```

### Success Criteria:
- ✅ Customer creation never fails
- ✅ Verification request succeeds (email queued)
- ✅ Failed jobs are logged and can be retried
- ✅ Card functionality (stamping) works normally
- ✅ Failed emails can be retried after fixing SendGrid

---

## Test 4: Queue Worker Stopped

**Purpose:** Verify queued emails accumulate when worker is stopped.

### Steps:

1. **Stop queue worker:**
   ```bash
   sudo supervisorctl stop kawhe-queue-worker:*
   # OR
   sudo systemctl stop kawhe-queue-worker
   ```

2. **Create multiple customers and request verification:**
   - Create 3-5 customers
   - Request verification email for each
   - **Expected:** All requests succeed (emails queued)

3. **Check queue:**
   ```bash
   php artisan queue:monitor
   # Expected: Shows pending jobs in queue
   ```

4. **Check database:**
   ```bash
   php artisan tinker
   >>> DB::table('jobs')->count();
   # Expected: Number of queued emails
   ```

5. **Verify customer functionality:**
   - Try to stamp cards
   - **Expected:** All functionality works normally

6. **Start queue worker:**
   ```bash
   sudo supervisorctl start kawhe-queue-worker:*
   # OR
   sudo systemctl start kawhe-queue-worker
   ```

7. **Monitor queue processing:**
   ```bash
   tail -f storage/logs/queue-worker.log
   # OR
   tail -f storage/logs/laravel.log
   # Expected: Jobs being processed
   ```

8. **Check queue again:**
   ```bash
   php artisan queue:monitor
   # Expected: Queue empty (all jobs processed)
   ```

9. **Check emails:**
   - Check inboxes for all test emails
   - **Expected:** All verification emails received

### Success Criteria:
- ✅ Customer creation works when queue worker is stopped
- ✅ Emails accumulate in queue
- ✅ Queue worker processes all accumulated jobs when started
- ✅ All emails are sent after worker starts

---

## Test 5: Verification Link Expiry

**Purpose:** Verify expired tokens are handled gracefully.

### Steps:

1. **Request verification email:**
   - Create customer and request verification
   - **Expected:** Email received

2. **Wait for token expiry:**
   - Tokens expire after 60 minutes
   - For testing, manually expire in database:
   ```bash
   php artisan tinker
   >>> $customer = App\Models\Customer::where('email', 'test@example.com')->first();
   >>> $customer->update(['email_verification_expires_at' => now()->subMinute()]);
   ```

3. **Click expired verification link:**
   - Open the verification URL
   - **Expected:** Redirected with error "Invalid or expired verification token"
   - **Expected:** No 500 error, friendly error message

4. **Request new verification email:**
   - Click "Verify Email" again
   - **Expected:** New email sent with new token

5. **Verify with new token:**
   - Click new verification link
   - **Expected:** Email verified successfully

### Success Criteria:
- ✅ Expired tokens show friendly error (no 500)
- ✅ Users can request new verification email
- ✅ New tokens work correctly

---

## Test 6: Multiple Rewards with Verification

**Purpose:** Verify email verification works with multiple rewards system.

### Steps:

1. **Create and verify customer:**
   - Create customer with email
   - Verify email

2. **Earn multiple rewards:**
   - Stamp card to earn 2+ rewards (e.g., 12 stamps on 5-target card)

3. **Redeem first reward:**
   - Click "Redeem My Reward"
   - Show QR code to merchant
   - **Expected:** First reward redeemed successfully

4. **Redeem second reward:**
   - Click "Redeem My Reward" again
   - Show QR code to merchant
   - **Expected:** Second reward redeemed successfully

5. **Verify unverified customer cannot redeem:**
   - Create new unverified customer
   - Earn rewards
   - Try to redeem
   - **Expected:** Error "You must verify your email address"

### Success Criteria:
- ✅ Verified customers can redeem multiple rewards
- ✅ Unverified customers cannot redeem
- ✅ Verification status persists across multiple redemptions

---

## Quick Verification Commands

```bash
# Check queue status
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Test email configuration
php artisan kawhe:mail-test your-email@example.com

# Check queue worker status (supervisor)
sudo supervisorctl status kawhe-queue-worker:*

# Check queue worker status (systemd)
sudo systemctl status kawhe-queue-worker

# View logs
tail -f storage/logs/laravel.log
tail -f storage/logs/queue-worker.log
```

---

## Common Issues and Solutions

### Issue: Emails not sending
**Solution:**
1. Check queue worker is running
2. Check SendGrid API key is correct
3. Check SendGrid account has credits
4. Check logs: `tail -f storage/logs/laravel.log`

### Issue: Verification links not working
**Solution:**
1. Check `APP_URL` matches production domain
2. Check `APP_ENV=production` (forces HTTPS)
3. Verify link in email matches `APP_URL`

### Issue: Queue worker not processing
**Solution:**
1. Check worker is running: `sudo supervisorctl status`
2. Check logs for errors
3. Restart worker: `sudo supervisorctl restart kawhe-queue-worker:*`

### Issue: Failed jobs accumulating
**Solution:**
1. Check error in logs
2. Fix underlying issue (e.g., SendGrid API key)
3. Retry jobs: `php artisan queue:retry all`
