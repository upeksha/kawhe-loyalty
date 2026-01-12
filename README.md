# Kawhe Loyalty

A Progressive Web App (PWA) loyalty card system built with Laravel 11. Enable merchants to create digital loyalty programs where customers earn stamps and redeem rewards with real-time updates.

## Features

- 🏪 **Multi-Store Management**: Merchants can create and manage multiple stores
- 🎨 **Custom Branding**: Upload logos, set brand colors, and customize card backgrounds
- 📱 **PWA Support**: Works offline with service worker caching
- ⚡ **Real-time Updates**: Live synchronization via Laravel Reverb (WebSockets)
- 🔒 **Secure Redemption**: Email verification required for reward redemption
- 📊 **Transaction Ledger**: Immutable audit trail of all point transactions
- 🛡️ **Data Integrity**: Idempotency, optimistic locking, and rate limiting
- 📧 **Email Integration**: SendGrid SMTP for verification emails

## Quick Start

See [RUN_PROJECT.md](RUN_PROJECT.md) for detailed setup instructions.

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Build assets
npm run build

# Start servers (in separate terminals)
php artisan serve
php artisan reverb:start
```

## Documentation

- **[TECHNICAL_DOCUMENTATION.md](TECHNICAL_DOCUMENTATION.md)** - Complete technical documentation covering architecture, features, API endpoints, and more
- **[RUN_PROJECT.md](RUN_PROJECT.md)** - Setup and running instructions
- **[SENDGRID_SETUP.md](SENDGRID_SETUP.md)** - Email configuration guide
- **[PRODUCTION_EMAIL_SETUP.md](PRODUCTION_EMAIL_SETUP.md)** - Production email setup with SendGrid and queue workers

## Tech Stack

- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: Tailwind CSS, Alpine.js, Vite
- **Real-time**: Laravel Reverb (WebSockets)
- **Database**: SQLite (dev) / PostgreSQL/MySQL (production)
- **Email**: SendGrid SMTP
- **Testing**: Pest PHP

## Key User Flows

1. **Merchant Onboarding**: Register → Create Store → Get Join Link
2. **Customer Enrollment**: Receive Join Link → Enter Details → Get Loyalty Card
3. **Stamping**: Merchant Scans QR → Stamps Added → Real-time Update
4. **Redemption**: Reach Target → Verify Email → Scan Redeem QR → Reward Redeemed

## Security

- Rate limiting on all critical endpoints
- Idempotency keys prevent duplicate transactions
- Optimistic locking prevents race conditions
- Email verification required for redemption
- Immutable transaction ledger for audit trail

## License

MIT License

## Production Deployment

### After Deploying from Git

Run these commands on your server:

```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader

# 2. Run migrations (creates jobs table if needed)
php artisan migrate --force

# 3. Clear and cache config
php artisan config:clear
php artisan config:cache

# 4. Restart queue worker (if using supervisor/systemd)
sudo supervisorctl restart kawhe-queue-worker:*
# OR
sudo systemctl restart kawhe-queue-worker

# 5. Test email configuration
php artisan kawhe:mail-test your-email@example.com
```

See **[PRODUCTION_EMAIL_SETUP.md](PRODUCTION_EMAIL_SETUP.md)** for complete production email setup instructions including:
- SendGrid SMTP configuration
- Queue worker setup (Supervisor/systemd)
- Monitoring and troubleshooting
- Email testing commands

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
