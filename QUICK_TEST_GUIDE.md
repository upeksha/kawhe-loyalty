# Quick Test Guide - Email Verification & Reward Claiming

## 🎯 Quick Test Steps

### 1. Start the App
```bash
# Terminal 1
php artisan serve --port=8000

# Terminal 2 (optional)
php artisan reverb:start
```

### 2. Create Test Customer
1. Visit: http://localhost:8000
2. Register merchant → Create store → Get join link
3. Join as customer with email: `test@example.com`

### 3. Test Email Verification

**Option A: Use the Helper Script**
```bash
# After clicking "Verify Email" on card page
php test-verification.php test@example.com
```
This will:
- Show the verification link from logs
- Option to manually verify if needed
- Show account status

**Option B: Manual Method**
1. Click "Verify Email" on customer card
2. Check logs: `tail -f storage/logs/laravel.log`
3. Find the verification URL in the log
4. Copy and paste in browser

**Option C: Quick Verify (Skip Email)**
```bash
php artisan tinker
```
```php
$customer = \App\Models\Customer::where('email', 'test@example.com')->first();
$customer->update(['email_verified_at' => now()]);
```

### 4. Test Reward Claiming

1. **Add stamps** via scanner until reward is available
2. **Check card** - should show redeem QR (if email verified)
3. **Scan redeem QR** from scanner
4. **Verify** - stamps deducted, reward marked as redeemed

## ✅ Verification Checklist

- [ ] Email verification banner appears on card
- [ ] "Verify Email" button works
- [ ] Email appears in logs (`storage/logs/laravel.log`)
- [ ] Verification link works
- [ ] Card shows email is verified
- [ ] Reward redeem QR appears (when reward available + email verified)
- [ ] Redemption works correctly
- [ ] Stamps deducted after redemption

## 🔍 Check Status

```bash
# Check customer verification
php artisan tinker
```
```php
$customer = \App\Models\Customer::where('email', 'test@example.com')->first();
echo "Verified: " . ($customer->email_verified_at ? 'Yes' : 'No') . PHP_EOL;

$account = $customer->loyaltyAccounts()->first();
echo "Stamps: {$account->stamp_count}\n";
echo "Reward Available: " . ($account->reward_available_at ? 'Yes' : 'No') . PHP_EOL;
echo "Can Redeem: " . ($account->reward_available_at && $account->customer->email_verified_at && !$account->reward_redeemed_at ? 'Yes' : 'No') . PHP_EOL;
```

## 📝 Current Configuration

- **Local**: `MAIL_MAILER=log` (emails to logs)
- **Production**: `MAIL_MAILER=smtp` (SendGrid)
- **Email sends**: Synchronously in local, queued in production
- **No breaking changes**: All existing functionality preserved

## 🚀 Production Ready

When you deploy to Digital Ocean:
- Just change `MAIL_MAILER=smtp` in production `.env`
- Configure SendGrid credentials
- Run queue worker: `php artisan queue:work`
- Everything else works the same!
