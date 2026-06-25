# Kawhe Loyalty

A Progressive Web App (PWA) loyalty platform built with Laravel 12. Merchants configure loyalty cards, onboard customers via QR/join links, and scan to stamp/redeem rewards. Customers get web cards with optional Apple Wallet and Google Wallet passes and real-time updates.

## Features

- 🏪 **Multi-Store & Multi-Card**: Stores can run multiple loyalty cards (programs), each with its own join link and QR
- 🎨 **Custom Branding**: Required logos, brand colors, and wallet assets during setup
- 📱 **PWA Support**: Works offline with service worker caching
- ⚡ **Real-time Updates**: Live synchronization via Laravel Reverb (WebSockets)
- 🔒 **Secure Redemption**: Email verification required for reward redemption (configurable per card)
- 📊 **Transaction Ledger**: Immutable audit trail of all point transactions
- 🛡️ **Data Integrity**: Idempotency, optimistic locking, and rate limiting
- 📧 **Email Integration**: SendGrid SMTP for verification and welcome emails
- 💳 **Subscription Billing**: Stripe integration via Laravel Cashier for merchant subscriptions
- 🍎 **Apple Wallet / Google Wallet**: Pass generation with per-account serial numbers and auto-updates

## Quick Start

See [docs/RUN_PROJECT.md](docs/RUN_PROJECT.md) for detailed setup instructions.

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

**Start here for new developers:**
- **[docs/DEVELOPER_HANDOVER.md](docs/DEVELOPER_HANDOVER.md)** — Product behavior, domain model, flows, and where to edit

**Reference:**
- **[docs/FULL_SYSTEM_DOCUMENTATION.md](docs/FULL_SYSTEM_DOCUMENTATION.md)** — End-to-end system reference
- **[docs/MERCHANT_ONBOARDING_AND_CARD_DESIGN_FLOW.md](docs/MERCHANT_ONBOARDING_AND_CARD_DESIGN_FLOW.md)** — Wizard and branding setup detail
- **[docs/TECHNICAL_DOCUMENTATION.md](docs/TECHNICAL_DOCUMENTATION.md)** — Architecture, API, security, deployment
- **[AGENTS.md](AGENTS.md)** — Delivery guardrails for AI agents and contributors
- **[docs/CHANGES_SUMMARY.md](docs/CHANGES_SUMMARY.md)** — Recent platform changes

**Setup & ops:**
- **[docs/RUN_PROJECT.md](docs/RUN_PROJECT.md)** — Local setup and running
- **[docs/RELEASE_WORKFLOW.md](docs/RELEASE_WORKFLOW.md)** — Release process
- **[docs/SELF_SERVE_LAUNCH_CHECKLIST.md](docs/SELF_SERVE_LAUNCH_CHECKLIST.md)** — Pre-launch QA
- **[docs/SENDGRID_SETUP.md](docs/SENDGRID_SETUP.md)** — Email configuration
- **[docs/BILLING_SETUP.md](docs/BILLING_SETUP.md)** — Stripe billing setup
- **[docs/APPLE_WALLET_SETUP.md](docs/APPLE_WALLET_SETUP.md)** — Apple Wallet setup

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Tailwind CSS, Alpine.js, Vite
- **Real-time**: Laravel Reverb (WebSockets)
- **Database**: SQLite (dev) / PostgreSQL/MySQL (production)
- **Email**: SendGrid SMTP
- **Billing**: Laravel Cashier (Stripe)
- **Testing**: Pest PHP

## Key User Flows

1. **Merchant Onboarding**: Register → 4-step wizard (basics, branding, customer form, card ready) → QR/join link
2. **Customer Enrollment**: Join link/QR → signup or find existing card → web loyalty card → optional wallet
3. **Stamping**: Merchant scans QR → stamps added → real-time + wallet sync
4. **Redemption**: Reach target → verify email (if required) → scan redeem QR → reward redeemed

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

# 2. Run migrations (creates jobs table and Cashier tables if needed)
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

# 6. Run production readiness check
php artisan health:check
```

See **[docs/PRODUCTION_EMAIL_SETUP.md](docs/PRODUCTION_EMAIL_SETUP.md)** for complete production email setup instructions.
