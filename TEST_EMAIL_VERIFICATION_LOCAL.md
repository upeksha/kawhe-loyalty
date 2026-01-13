# Testing Email Verification & Reward Claiming Locally

## Current Setup ✅

- **MAIL_MAILER**: `log` (emails written to logs, not sent)
- **APP_ENV**: `local` (emails send synchronously)
- **Production**: Will use SendGrid (configured in `.env` for production)

## Testing Flow

### Step 1: Start the App

Make sure these are running:
```bash
# Terminal 1
php artisan serve --port=8000

# Terminal 2 (optional for real-time updates)
php artisan reverb:start
```

### Step 2: Create Test Data

1. **Register a merchant** (if you don't have one):
   - Visit: http://localhost:8000/register
   - Create account

2. **Create a store**:
   - Visit: http://localhost:8000/merchant/stores/create
   - Set reward target to **5 stamps** (for quick testing)
   - Save the store

3. **Get join link**:
   - Visit: http://localhost:8000/merchant/stores/{store_id}/qr
   - Copy the join link

### Step 3: Create Customer Account

1. **Open join link** in a new tab/incognito window
2. **Click "New Customer"**
3. **Enter details**:
   - Name: Test Customer
   - Email: test@example.com (or your real email)
4. **Submit** → You'll be redirected to the loyalty card

### Step 4: Test Email Verification

1. **On the customer card page**, you should see a blue banner asking to verify email
2. **Click "Verify Email"** button
3. **Check the logs** for the verification email:
   ```bash
   tail -f storage/logs/laravel.log
   ```
4. **Look for the verification link** in the log output. It will look like:
   ```
   http://localhost:8000/verify-email/{token}?card={public_token}
   ```
5. **Copy the full URL** from the logs
6. **Paste it in your browser** to verify the email
7. **You should be redirected** back to the card page with "Email verified successfully!"

### Step 5: Test Reward Claiming

1. **Add stamps** to reach the reward target:
   - Go to: http://localhost:8000/merchant/scanner
   - Select your store
   - Scan the customer's stamp QR code multiple times until you reach the target (e.g., 5 stamps)

2. **Check the customer card** - you should see:
   - Reward is now available
   - A redeem QR code is shown (not the lock icon)

3. **Redeem the reward**:
   - Go back to scanner
   - Scan the **redeem QR code** (starts with `REDEEM:`)
   - Should show success message

4. **Verify redemption**:
   - Check customer card - reward should show as redeemed
   - Stamps should be deducted
   - New cycle starts

## Quick Test Script

Here's a quick way to test without waiting:

### Option 1: Manually Verify Email (Skip Email Step)

```bash
php artisan tinker
```

Then run:
```php
$customer = \App\Models\Customer::where('email', 'test@example.com')->first();
$customer->update(['email_verified_at' => now()]);
```

Now you can test redemption immediately!

### Option 2: Extract Verification Link from Logs

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep -i "verify-email"

# Or search for the link
grep -o "http://localhost:8000/verify-email/[^ ]*" storage/logs/laravel.log | tail -1
```

## Expected Behavior

### ✅ Email Verification Flow
- [ ] Blue banner appears on card if email not verified
- [ ] "Verify Email" button works
- [ ] Email content appears in logs
- [ ] Verification link works when clicked
- [ ] Card updates to show email is verified
- [ ] Redeem QR appears (if reward available)

### ✅ Reward Claiming Flow
- [ ] Stamps can be added via scanner
- [ ] Reward becomes available when target reached
- [ ] Redeem QR code appears (if email verified)
- [ ] Lock icon appears if email not verified
- [ ] Redemption works when scanning redeem QR
- [ ] Stamps are deducted after redemption
- [ ] New cycle starts

## Troubleshooting

### Email Not in Logs?
- Check: `storage/logs/laravel.log`
- Make sure `MAIL_MAILER=log` in `.env`
- Clear cache: `php artisan config:clear`

### Verification Link Not Working?
- Check token hasn't expired (60 minutes)
- Make sure you copy the FULL URL including `?card=...`
- Check logs for errors

### Can't Redeem Reward?
- Verify email is verified: Check `email_verified_at` in database
- Make sure reward is available: Check `reward_available_at` is set
- Make sure reward not already redeemed: Check `reward_redeemed_at` is null

### Check Database Directly

```bash
php artisan tinker
```

```php
// Check customer verification
$customer = \App\Models\Customer::where('email', 'test@example.com')->first();
echo "Email verified: " . ($customer->email_verified_at ? 'Yes' : 'No') . PHP_EOL;

// Check reward status
$account = $customer->loyaltyAccounts()->first();
echo "Stamps: " . $account->stamp_count . PHP_EOL;
echo "Reward available: " . ($account->reward_available_at ? 'Yes' : 'No') . PHP_EOL;
echo "Reward redeemed: " . ($account->reward_redeemed_at ? 'Yes' : 'No') . PHP_EOL;
```

## Production Notes

When deploying to Digital Ocean:
- Set `MAIL_MAILER=smtp` in production `.env`
- Configure SendGrid credentials
- Run `php artisan queue:work` for email processing
- Everything else works the same way!
