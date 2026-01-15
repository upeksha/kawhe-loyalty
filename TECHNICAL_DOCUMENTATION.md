# Kawhe Loyalty - Technical Documentation

## Table of Contents
1. [Overview](#overview)
2. [Architecture & Tech Stack](#architecture--tech-stack)
3. [Core Features](#core-features)
4. [User Flows](#user-flows)
5. [Database Schema](#database-schema)
6. [API Endpoints](#api-endpoints)
7. [Real-time Features](#real-time-features)
8. [Security Features](#security-features)
9. [Data Integrity](#data-integrity)
10. [Setup & Deployment](#setup--deployment)
11. [Appendix: Consolidated Project Docs](#appendix-consolidated-project-docs)

---

## Overview

**Kawhe Loyalty** is a Progressive Web App (PWA) loyalty card system built with Laravel 11. It enables merchants to create digital loyalty programs where customers earn stamps and redeem rewards. The system features real-time updates, email verification, store branding, and robust data integrity mechanisms.

### Key Capabilities
- **Merchant Management**: Create and manage stores with custom branding
- **Customer Enrollment**: Join loyalty programs via unique join links
- **Digital Stamping**: Scan QR codes to add stamps to customer cards
- **Multiple Rewards**: Customers can accumulate multiple rewards (e.g., 18 stamps on 9-target card = 2 rewards)
- **Flexible Redemption**: Merchants can redeem 1, 2, 3... or all available rewards per scan
- **Real-time Updates**: Live synchronization via Laravel Reverb (WebSockets)
- **Transaction Ledger**: Immutable audit trail of all point transactions

---

## Architecture & Tech Stack

### Backend
- **Framework**: Laravel 11 (PHP 8.2+)
- **Database**: SQLite (development) / PostgreSQL/MySQL (production)
- **Real-time**: Laravel Reverb (WebSocket server)
- **Queue System**: Database queues for email processing
- **Email**: SendGrid SMTP
- **QR Code Generation**: SimpleSoftwareIO/QrCode

### Frontend
- **CSS Framework**: Tailwind CSS 3
- **JavaScript**: Alpine.js for reactive UI
- **Build Tool**: Vite 7
- **QR Code Scanning**: html5-qrcode library
- **WebSocket Client**: Laravel Echo + Pusher JS

### Infrastructure
- **PWA**: Service Worker for offline support
- **Asset Management**: Vite with hot module replacement (HMR)
- **Testing**: Pest PHP testing framework

---

## Core Features

### 1. Merchant Dashboard
**Location**: `/merchant/dashboard`

Merchants can:
- View dashboard overview
- Create and manage multiple stores
- Customize store branding (logo, brand color, background color)
- Generate join links for customers
- View customer list and individual cards
- Access QR scanner for stamping/redeeming

### 2. Store Management
**Routes**: `/merchant/stores/*` and `/merchant/onboarding/store`

**Features**:
- Create stores with custom names, addresses, and reward configurations
- **Unified Forms**: Onboarding and regular create forms have identical fields and styling
  - Both support: Store Name, Address, Reward Target, Reward Title
  - Both support: Brand Color, Background Color, Logo Upload
  - Onboarding form includes welcome message but uses same structure
- Set reward target (e.g., "10 stamps = Free Coffee")
- Upload store logo (PNG, JPG, WebP, max 2MB)
- Set brand color (hex color picker + text input, used for card border accent)
- Set background color (hex color picker + text input, used for card page background)
- Generate unique join tokens and QR codes
- Each store has a unique slug and join token

**Store Configuration**:
- `name`: Store name
- `slug`: URL-friendly identifier (auto-generated)
- `address`: Store location
- `reward_target`: Number of stamps needed for reward
- `reward_title`: Reward description (e.g., "Free Coffee")
- `join_token`: Secret token for join links (32 characters)
- `brand_color`: Hex color for card branding
- `logo_path`: Path to uploaded logo image
- `background_color`: Hex color for card page background

### 3. Customer Enrollment
**Routes**: `/join/{slug}*`

**Flow**:
1. Customer receives join link: `/join/{slug}?t={join_token}`
2. Customer chooses "New Customer" or "Existing Customer"
3. **New Customer**: Enters name and email/phone
4. **Existing Customer**: Enters email to lookup existing card
5. System creates or finds `Customer` record
6. System creates `LoyaltyAccount` linking customer to store
7. Customer is redirected to their loyalty card page

**Key Points**:
- Customers can have multiple loyalty accounts (one per store)
- Email or phone number is required
- System reuses existing customer records when email matches
- Each loyalty account gets a unique `public_token` (40 characters)

### 4. Loyalty Card Display
**Route**: `/c/{public_token}`

**Features**:
- Wallet-style pass design with always-visible QR code
- **Store Branding**:
  - Store logo displayed at top of card (if `logo_path` is set)
  - Brand color used as card border accent (3px solid border)
  - Background color applied to entire page background
  - All branding elements customizable per store
- Customer name and masked card ID
- Progress visualization (circular stamp dots)
- Reward status (available, redeemed, locked)
- Recent activity/transaction history
- Email verification status
- Real-time updates via WebSocket
- Auto-refresh after reward redemption to ensure UI consistency

**Card States**:
- **Collecting**: `reward_balance = 0`, stamps < target, reward not available
- **Reward Available**: `reward_balance > 0`, reward(s) available to redeem
- **Multiple Rewards**: `reward_balance > 1`, customer can redeem multiple rewards
- **Reward Redeemed**: Reward was redeemed, but `reward_balance` may still be > 0 if multiple rewards existed

### 5. QR Code Scanner
**Route**: `/merchant/scanner`

**Functionality**:
- **Auto-start**: Scanner automatically starts on page load, requesting camera permission immediately
- **Camera Selection**:
  - Defaults to back camera (`facingMode: "environment"`)
  - Automatically switches to saved camera preference (stored in `localStorage`)
  - Always-visible "Switch Camera" button when multiple cameras available
- Merchant selects store from dropdown
- Scans customer's QR code (stamp or redeem)
- System validates token and processes transaction
- Real-time feedback with customer name and stamp count
- **Visual Cooldown**: After successful scan, 3-second cooldown overlay appears with countdown
  - Scanner pauses during cooldown
  - Prevents duplicate scans automatically
  - Resumes automatically after cooldown
- Upload image fallback available (hidden behind "Having trouble?" link)

**QR Code Formats**:
- **Stamping**: `LA:{public_token}` (always visible on card)
- **Redemption**: `REDEEM:{redeem_token}` (only shown when reward available)

### 6. Stamping Process
**Endpoint**: `POST /stamp`

**Process**:
1. Merchant scans customer's stamp QR code
2. System validates:
   - Token belongs to selected store (auto-detected from token)
   - Server-side idempotency window (5 seconds) - prevents duplicate scans
   - Cooldown period has passed (30 seconds, with override option)
   - Store ownership verified
3. Database transaction:
   - Lock loyalty account row
   - Increment stamp count by `count` (default 1)
   - Calculate newly earned rewards: `floor(stamp_count / reward_target)`
   - Update `reward_balance`: `reward_balance += newlyEarned`
   - Update `stamp_count`: `stamp_count = stamp_count % reward_target`
   - Generate `redeem_token` if `reward_balance > 0` and token is null
   - Update version (optimistic locking)
   - Create ledger entry (`PointsTransaction`)
   - Create event record (`StampEvent`)
4. Broadcast real-time update via WebSocket
5. Return success response with receipt

**Multiple Rewards**: Stamps can accumulate beyond the target. For example:
- Card with `reward_target = 9`
- Customer has 18 stamps
- System calculates: `reward_balance = floor(18/9) = 2`, `stamp_count = 18 % 9 = 0`
- Customer now has 2 rewards available

**Idempotency**: 
- Uses idempotency keys to prevent duplicate processing
- Server-side idempotency window: Same store + same account + same action within 5 seconds = duplicate (ignored)

**Cooldown Override**:
- Default: 30-second cooldown between stamps
- If within cooldown, returns HTTP 409 with `allow_override: true`
- Merchant can confirm override to proceed (still subject to server-side idempotency)

### 7. Reward Redemption
**Endpoint**: `POST /redeem`
**Info Endpoint**: `POST /redeem/info` (get reward balance before showing modal)

**Process**:
1. Merchant scans customer's redeem QR code
2. Frontend calls `POST /redeem/info` to fetch `reward_balance`
3. If `reward_balance > 1`:
   - Modal shows quantity selector (1, 2, 3... or "Redeem All")
   - Merchant selects quantity to redeem
4. If `reward_balance === 1`:
   - Simple confirmation modal (no quantity selector)
5. System validates:
   - Redeem token is valid and not expired
   - `quantity` doesn't exceed `reward_balance`
   - Store ownership verified
6. Database transaction:
   - Lock loyalty account row
   - Consume specified quantity: `reward_balance -= quantity`
   - Set `reward_redeemed_at` timestamp
   - If `reward_balance > 0`: Keep `redeem_token` (persists for multiple redemptions)
   - If `reward_balance === 0`: Clear `redeem_token` and `reward_available_at`
   - Do NOT deduct `stamp_count` (represents progress toward next reward)
   - Create ledger entry with `points = -reward_target * quantity`
   - Create event record
7. Broadcast real-time update
8. Return success response with remaining rewards

**Multiple Rewards Example**:
- Customer has 3 rewards (`reward_balance = 3`)
- Merchant scans → modal shows "Customer has 3 rewards available"
- Merchant selects 1 → redeems 1, 2 remain
- QR code remains valid (same `redeem_token`)
- Merchant can scan again to redeem more

**Token Persistence**: 
- `redeem_token` persists across multiple redemptions
- Only cleared when `reward_balance` reaches 0
- Prevents "invalid or expired" errors after partial redemption

### 8. Email Verification
**Routes**: `/c/{public_token}/verify-email/*`

**Process**:
1. Customer requests verification email from card page
2. System generates 40-character random token
3. Email sent via SendGrid with verification link
4. Customer clicks link: `/verify-email/{token}?card={public_token}`
5. System verifies token and sets `email_verified_at` timestamp
6. Customer can now redeem rewards

**Rate Limiting**: 3 requests per 10 minutes per card
**Token Expiry**: 60 minutes

---

## User Flows

### Merchant Onboarding Flow
```
1. Merchant visits /start
2. Clicks "Get Started"
3. Registers account (/register)
4. Redirected to /merchant/onboarding/store
5. Creates first store
6. Redirected to /merchant/dashboard
```

### Customer Join Flow (New)
```
1. Customer receives join link: /join/{slug}?t={token}
2. Clicks "New Customer"
3. Fills form (name, email/phone)
4. Submits → POST /join/{slug}/new
5. System creates Customer + LoyaltyAccount
6. Redirected to /c/{public_token}
```

### Customer Join Flow (Existing)
```
1. Customer receives join link
2. Clicks "I Already Have a Card"
3. Enters email
4. System looks up existing Customer
5. Checks if LoyaltyAccount exists for this store
6. If exists → redirect to card
7. If not → error message
```

### Stamping Flow
```
1. Merchant opens /merchant/scanner
2. Selects store from dropdown
3. Scans customer's stamp QR code
4. Frontend sends POST /stamp with token
5. Backend processes in transaction:
   - Validates token
   - Increments stamp count
   - Creates ledger entry
   - Broadcasts WebSocket event
6. Customer's card page receives real-time update
7. UI updates without page refresh
```

### Redemption Flow
```
1. Customer accumulates stamps beyond target
2. System calculates reward_balance (e.g., 18 stamps / 9 target = 2 rewards)
3. Reward becomes available (reward_balance > 0)
4. Merchant scans redeem QR code
5. Frontend fetches reward balance via POST /redeem/info
6. If reward_balance > 1:
   - Modal shows quantity selector
   - Merchant selects quantity (1, 2, 3... or All)
7. Backend processes redemption:
   - Validates redeem token (persists across redemptions)
   - Validates quantity doesn't exceed reward_balance
   - Consumes specified quantity: reward_balance -= quantity
   - Keeps redeem_token if reward_balance > 0
   - Clears redeem_token only if reward_balance = 0
   - Does NOT deduct stamp_count (represents progress toward next reward)
   - Broadcasts update
8. Customer's card updates in real-time
9. QR code remains valid if rewards remain
10. Merchant can scan again to redeem remaining rewards
```

---

## Database Schema

### Core Tables

#### `users`
Merchant accounts (Laravel Breeze authentication)
- `id`, `name`, `email`, `password`, `is_super_admin`, `email_verified_at`, `timestamps`

#### `stores`
Merchant store configurations
- `id`, `user_id`, `name`, `slug`, `address`, `reward_target`, `reward_title`
- `join_token` (32 chars), `brand_color`, `logo_path`, `background_color`, `timestamps`

#### `customers`
Customer profiles (can have multiple loyalty accounts)
- `id`, `name`, `email`, `phone`, `email_verified_at`, `email_verification_token`
- `email_verification_sent_at`, `timestamps`

#### `loyalty_accounts`
Links customers to stores, tracks stamp progress
- `id`, `store_id`, `customer_id`, `stamp_count`, `public_token` (40 chars)
- `reward_balance` (integer, default 0) - Number of rewards available
- `redeem_token` (40 chars, nullable) - Token for redemption (persists until all rewards redeemed)
- `last_stamped_at`, `reward_available_at`, `reward_redeemed_at`
- `version` (optimistic locking), `timestamps`

**Key Fields**:
- `stamp_count`: Current progress toward next reward (0 to reward_target-1)
- `reward_balance`: Number of rewards available (can be > 1)
- `redeem_token`: Persists across multiple redemptions, only cleared when `reward_balance = 0`

#### `stamp_events`
Event log of all stamp/redeem actions
- `id`, `loyalty_account_id`, `store_id`, `user_id`, `type` (stamp/redeem)
- `count`, `idempotency_key`, `user_agent`, `ip_address`, `timestamps`

#### `points_transactions`
Immutable ledger of all point changes (audit trail)
- `id`, `loyalty_account_id`, `store_id`, `user_id`, `type` (earn/redeem)
- `points` (positive for earn, negative for redeem), `idempotency_key`
- `metadata` (JSON), `user_agent`, `ip_address`, `timestamps`

### Relationships
```
User → hasMany → Store
Store → belongsTo → User
Store → hasMany → LoyaltyAccount
Customer → hasMany → LoyaltyAccount
LoyaltyAccount → belongsTo → Store
LoyaltyAccount → belongsTo → Customer
LoyaltyAccount → hasMany → PointsTransaction
LoyaltyAccount → hasMany → StampEvent
```

---

## API Endpoints

### Public Endpoints

#### Customer Card
- `GET /c/{public_token}` - Display loyalty card
- `GET /api/card/{public_token}` - Get card data (JSON)
- `GET /api/card/{public_token}/transactions` - Get transaction history

#### Join Flow
- `GET /join/{slug}` - Join landing page (requires `?t={token}`)
- `GET /join/{slug}/new` - New customer form
- `POST /join/{slug}/new` - Create new customer account
- `GET /join/{slug}/existing` - Existing customer lookup form
- `POST /join/{slug}/existing` - Lookup existing customer

#### Email Verification
- `POST /c/{public_token}/verify-email/send` - Send verification email
- `GET /verify-email/{token}` - Verify email token

### Authenticated Endpoints (Merchant)

#### Stamping & Redemption
- `POST /stamp` - Add stamps to card
  - Body: `{token, store_id?, count?, idempotency_key?, override_cooldown?}`
  - `store_id` is optional (auto-detected from token)
  - `override_cooldown`: Boolean to bypass 30s cooldown
- `POST /redeem/info` - Get reward balance before redemption
  - Body: `{token, store_id}`
  - Returns: `{success, reward_balance, reward_title, customer_name}`
- `POST /redeem` - Redeem reward(s)
  - Body: `{token, store_id, quantity?, idempotency_key?}`
  - `quantity`: Number of rewards to redeem (default 1)
  - Returns remaining rewards in response

#### Store Management
- `GET /merchant/stores` - List stores
- `GET /merchant/stores/create` - Create store form
- `POST /merchant/stores` - Create store
- `GET /merchant/stores/{store}/edit` - Edit store form
- `PUT /merchant/stores/{store}` - Update store
- `DELETE /merchant/stores/{store}` - Delete store
- `GET /merchant/stores/{store}/qr` - View store QR codes

#### Customer Management
- `GET /merchant/customers` - List all customers
- `GET /merchant/customers/{loyaltyAccount}` - View customer card

#### Scanner
- `GET /merchant/scanner` - Scanner interface

### Response Formats

**Success Response (Stamping)**:
```json
{
  "status": "success",
  "success": true,
  "message": "Successfully added 1 stamp!",
  "storeName": "Coffee Shop",
  "store_id_used": 1,
  "store_name_used": "Coffee Shop",
  "store_switched": false,
  "customerLabel": "John Doe",
  "stampCount": 5,
  "rewardBalance": 0,
  "rewardTarget": 10,
  "rewardAvailable": false,
  "stampsRemaining": 5,
  "receipt": {
    "transaction_id": 123,
    "timestamp": "2026-01-11T19:00:00Z",
    "stamps_added": 1,
    "new_total": 5
  }
}
```

**Success Response (Redemption)**:
```json
{
  "success": true,
  "message": "Successfully redeemed 2 rewards! Enjoy your Free Coffee!",
  "customerLabel": "John Doe",
  "receipt": {
    "transaction_id": 124,
    "timestamp": "2026-01-11T19:05:00Z",
    "reward_title": "Free Coffee",
    "rewards_redeemed": 2,
    "remaining_rewards": 1,
    "remaining_stamps": 3
  }
}
```

**Cooldown Response**:
```json
{
  "status": "cooldown",
  "success": false,
  "message": "Stamped 12s ago",
  "seconds_since_last": 12,
  "cooldown_seconds": 30,
  "allow_override": true,
  "next_action": "confirm_override",
  "stampCount": 5,
  "rewardBalance": 0
}
```

**Duplicate Response**:
```json
{
  "status": "duplicate",
  "success": false,
  "message": "Duplicate scan ignored",
  "stampCount": 5,
  "seconds_since_last": 2
}
```

**Error Response**:
```json
{
  "message": "Validation error",
  "errors": {
    "token": ["This loyalty card belongs to 'Other Store' and is not valid for 'Coffee Shop'."]
  }
}
```

---

## Real-time Features

### WebSocket Architecture
- **Server**: Laravel Reverb (WebSocket server)
- **Client**: Laravel Echo + Pusher JS
- **Channel**: Private channel per loyalty card
- **Event**: `StampUpdated` event broadcasts account updates

### Implementation

**Server Side** (`app/Events/StampUpdated.php`):
```php
class StampUpdated implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return new PrivateChannel('loyalty-card.' . $this->account->public_token);
    }
}
```

**Client Side** (`resources/views/card/show.blade.php`):
```javascript
Echo.private(`loyalty-card.${publicToken}`)
    .listen('StampUpdated', (e) => {
        // Update UI with new stamp count
        updateCardData(e.account);
    });
```

### Update Flow
1. Merchant scans QR code
2. Backend processes transaction
3. `StampUpdated` event dispatched
4. Reverb broadcasts to channel
5. Customer's browser receives update
6. UI updates without page refresh

---

## Security Features

### Authentication & Authorization
- **Merchant Auth**: Laravel Breeze (email/password)
- **Store Ownership**: Middleware ensures merchants only access their stores
- **Super Admin**: Special role with access to all stores
- **Email Verification**: Required for reward redemption

### Rate Limiting
- **Stamping**: 10 stamps/minute per customer, 100/minute per store, 50/minute per IP
- **Email Verification**: 3 requests per 10 minutes per card
- **Join Lookup**: 10 requests per minute

### Data Protection
- **Idempotency Keys**: Prevent duplicate transaction processing
- **Optimistic Locking**: Version column prevents race conditions
- **Database Transactions**: All operations are atomic
- **Cooldown Periods**: 30-second cooldown between stamps on same card

### Token Security
- **Join Tokens**: 32-character random strings (required in URL)
- **Public Tokens**: 40-character random strings (for card access)
- **Redeem Tokens**: 40-character random strings (persist until all rewards redeemed)
  - Generated when `reward_balance` goes from 0 → 1
  - Persists across multiple redemptions
  - Only cleared when `reward_balance` reaches 0
  - Prevents "invalid or expired" errors after partial redemption
- **Email Verification Tokens**: 40-character random strings (expire after 60 minutes)

### Logging & Audit
- **User Agent**: Logged for every transaction
- **IP Address**: Logged for every transaction
- **Immutable Ledger**: `points_transactions` table provides complete audit trail
- **Event Log**: `stamp_events` table tracks all actions

---

## Data Integrity

### Idempotency
Every stamp/redeem operation includes an `idempotency_key`. If the same key is processed twice, the system returns the original result without creating duplicate transactions.

### Optimistic Locking
The `loyalty_accounts.version` column prevents race conditions. Each update increments the version, and concurrent updates are detected and prevented.

### Database Transactions
All stamp/redeem operations use database transactions to ensure atomicity:
- Either all operations succeed, or all are rolled back
- Prevents partial updates that could corrupt data

### Immutable Ledger
The `points_transactions` table serves as an immutable audit trail:
- Every point change is recorded
- Cannot be modified or deleted
- Includes metadata (before/after values, versions)
- Can be used to verify current balance

### Validation
- Store ownership verified before processing
- Token validation (public_token for stamps, redeem_token for redemption)
- Server-side idempotency window (5 seconds) prevents duplicate scans
- Cooldown checks prevent accidental double-stamping (30s, with override option)
- Quantity validation ensures redemption doesn't exceed available rewards
- Store auto-detection from token (prevents wrong store selection)

---

## Setup & Deployment

### Development Setup
See `RUN_PROJECT.md` for detailed setup instructions.

**Quick Start**:
```bash
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
php artisan reverb:start
```

### Environment Configuration
See `.env.example` for required variables.

**Key Variables**:
- `APP_URL`: Application URL
- `REVERB_*`: Reverb WebSocket configuration
- `MAIL_*`: SendGrid SMTP configuration
- `QUEUE_CONNECTION`: Set to `database` for queued emails

### Production Deployment
1. Set `APP_ENV=production`
2. Run `npm run build` to compile assets
3. Configure queue worker (Supervisor recommended)
4. Set up Reverb server
5. Configure SendGrid SMTP
6. Set up SSL certificates
7. Configure database (PostgreSQL/MySQL recommended)

### Email Configuration
See `SENDGRID_SETUP.md` for detailed SendGrid setup.

**Required**:
- SendGrid API key
- Verified sender email address
- Queue worker running (`php artisan queue:work`)

### Asset Management
- **Development**: `npm run dev` (Vite HMR)
- **Production**: `npm run build` (compiled assets)
- **ngrok**: Must build assets before accessing through ngrok

---

## Testing

### Running Tests
```bash
php artisan test
php artisan test --filter=EnrollmentTest
```

### Test Coverage
- Customer enrollment flows
- Stamping and redemption
- Email verification
- Store management
- Merchant onboarding
- Data integrity (idempotency, optimistic locking)

---

## Troubleshooting

### Common Issues

**Styles not loading through ngrok**:
- Run `npm run build` to compile assets
- Clear caches: `php artisan config:clear && php artisan view:clear`
- Ensure service worker is updated

**WebSocket not working**:
- Verify Reverb server is running
- Check `.env` Reverb configuration
- Ensure ports are accessible

**Emails not sending**:
- Verify SendGrid configuration
- Check queue worker is running
- Review `storage/logs/laravel.log`

**Real-time updates not appearing**:
- Check browser console for WebSocket errors
- Verify Reverb server is running
- Check Laravel logs for broadcast errors

---

## Recent Features (2026)

### Multiple Rewards System
- **Reward Stacking**: Customers can accumulate multiple rewards when stamps exceed target
  - Example: 18 stamps on 9-target card = 2 rewards available
  - `reward_balance` tracks available rewards (can be > 1)
  - `stamp_count` represents progress toward next reward (0 to reward_target-1)

### Flexible Redemption
- **Quantity Selector**: Merchants can redeem 1, 2, 3... or all available rewards per scan
- **Token Persistence**: `redeem_token` persists across multiple redemptions
  - Only cleared when all rewards are redeemed
  - Prevents QR code invalidation after partial redemption
- **Info Endpoint**: `POST /redeem/info` allows frontend to fetch reward balance before showing modal

### Enhanced Stamping
- **Server-Side Idempotency**: 5-second window prevents duplicate scans even if client idempotency key changes
- **Cooldown Override**: Merchants can override 30-second cooldown with confirmation
- **Store Auto-Detection**: System auto-detects store from token, prevents wrong store selection

### Scanner UX Improvements
- **Auto-Start**: Scanner automatically starts on page load, immediately requests camera permission
- **Back Camera Default**: Defaults to back camera (`facingMode: "environment"`) for better QR scanning
- **Camera Memory**: Remembers merchant's camera choice in `localStorage` (device-specific)
- **Visual Cooldown**: 3-second cooldown overlay with countdown after successful scans
  - Prevents duplicate scans automatically
  - Shows large countdown number (3... 2... 1...)
  - Scanner pauses during cooldown, resumes automatically
- **Switch Camera**: Always-visible button to toggle between front/back cameras
- **Upload Fallback**: Image upload option hidden behind "Having trouble?" link

### Store Branding on Customer Cards
- **Logo Display**: Store logo appears at top of customer card (if uploaded)
  - Size: 80x80px with semi-transparent background
  - Brand color border accent around logo
- **Brand Color**: Used as 3px solid border around entire card
  - Provides visual brand identity
  - Default: `#0EA5E9` if not set
- **Background Color**: Applied to entire page background
  - Creates immersive branded experience
  - Default: `#1F2937` if not set
- All three branding elements (logo, brand_color, background_color) are optional but recommended

## Future Enhancements

Potential features for future development:
- Multi-store customer dashboard
- Reward expiration dates
- Referral programs
- Push notifications
- Analytics dashboard
- Export transaction reports
- Custom reward tiers
- Gift card integration

---

## License

MIT License - See LICENSE file for details.

---

## Appendix: Consolidated Project Docs

This section embeds the repo's other Markdown documentation files into this single document for easier reference.

### Included documents

- [README.md](#appendix-readmemd)
- [RUN_PROJECT.md](#appendix-runprojectmd)
- [QUICK_START_LOCAL.md](#appendix-quickstartlocalmd)
- [QUICK_TEST_GUIDE.md](#appendix-quicktestguidemd)
- [CHANGES_SUMMARY.md](#appendix-changessummarymd)
- [BILLING_SETUP.md](#appendix-billingsetupmd)
- [BILLING_IMPLEMENTATION_SUMMARY.md](#appendix-billingimplementationsummarymd)
- [STRIPE_SYNC_IMPLEMENTATION.md](#appendix-stripesyncimplementationmd)
- [STRIPE_SYNC_CHANGES_SUMMARY.md](#appendix-stripesyncchangessummarymd)
- [STRIPE_SYNC_TEST_CHECKLIST.md](#appendix-stripesynctestchecklistmd)
- [STRIPE_DEPLOYMENT.md](#appendix-stripedeploymentmd)
- [GRANDFATHERING_IMPLEMENTATION.md](#appendix-grandfatheringimplementationmd)
- [SENDGRID_SETUP.md](#appendix-sendgridsetupmd)
- [SENDGRID_TROUBLESHOOTING.md](#appendix-sendgridtroubleshootingmd)
- [QUICK_FIX_SENDGRID.md](#appendix-quickfixsendgridmd)
- [PASSWORD_RESET_SETUP.md](#appendix-passwordresetsetupmd)
- [EMAIL_VERIFICATION_FIX.md](#appendix-emailverificationfixmd)
- [EMAIL_VERIFICATION_TEST_CHECKLIST.md](#appendix-emailverificationtestchecklistmd)
- [TEST_EMAIL_VERIFICATION_LOCAL.md](#appendix-testemailverificationlocalmd)
- [PRODUCTION_DEPLOY_CHECKLIST.md](#appendix-productiondeploychecklistmd)
- [PRODUCTION_EMAIL_SETUP.md](#appendix-productionemailsetupmd)
- [PRODUCTION_SERVICE_RESTART.md](#appendix-productionservicerestartmd)

---

## README.md
<a id="appendix-readmemd"></a>

**Source file**: `README.md`

### Kawhe Loyalty

A Progressive Web App (PWA) loyalty card system built with Laravel 11. Enable merchants to create digital loyalty programs where customers earn stamps and redeem rewards with real-time updates.

#### Features

- 🏪 **Multi-Store Management**: Merchants can create and manage multiple stores
- 🎨 **Custom Branding**: Upload logos, set brand colors, and customize card backgrounds
- 📱 **PWA Support**: Works offline with service worker caching
- ⚡ **Real-time Updates**: Live synchronization via Laravel Reverb (WebSockets)
- 🔒 **Secure Redemption**: Email verification required for reward redemption
- 📊 **Transaction Ledger**: Immutable audit trail of all point transactions
- 🛡️ **Data Integrity**: Idempotency, optimistic locking, and rate limiting
- 📧 **Email Integration**: SendGrid SMTP for verification emails
- 💳 **Subscription Billing**: Stripe integration via Laravel Cashier for merchant subscriptions

#### Quick Start

See [RUN_PROJECT.md](RUN_PROJECT.md) for detailed setup instructions.

```bash
### Install dependencies
composer install
npm install

### Setup environment
cp .env.example .env
php artisan key:generate

### Run migrations
php artisan migrate

### Build assets
npm run build

### Start servers (in separate terminals)
php artisan serve
php artisan reverb:start
```

#### Documentation

- **[TECHNICAL_DOCUMENTATION.md](TECHNICAL_DOCUMENTATION.md)** - Complete technical documentation covering architecture, features, API endpoints, and more
- **[RUN_PROJECT.md](RUN_PROJECT.md)** - Setup and running instructions
- **[SENDGRID_SETUP.md](SENDGRID_SETUP.md)** - Email configuration guide
- **[PRODUCTION_EMAIL_SETUP.md](PRODUCTION_EMAIL_SETUP.md)** - Production email setup with SendGrid and queue workers
- **[BILLING_SETUP.md](BILLING_SETUP.md)** - Stripe billing and subscription setup guide

#### Tech Stack

- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: Tailwind CSS, Alpine.js, Vite
- **Real-time**: Laravel Reverb (WebSockets)
- **Database**: SQLite (dev) / PostgreSQL/MySQL (production)
- **Email**: SendGrid SMTP
- **Billing**: Laravel Cashier (Stripe)
- **Testing**: Pest PHP

#### Key User Flows

1. **Merchant Onboarding**: Register → Create Store → Get Join Link
2. **Customer Enrollment**: Receive Join Link → Enter Details → Get Loyalty Card
3. **Stamping**: Merchant Scans QR → Stamps Added → Real-time Update
4. **Redemption**: Reach Target → Verify Email → Scan Redeem QR → Reward Redeemed

#### Security

- Rate limiting on all critical endpoints
- Idempotency keys prevent duplicate transactions
- Optimistic locking prevents race conditions
- Email verification required for redemption
- Immutable transaction ledger for audit trail

#### License

MIT License

#### Production Deployment

##### After Deploying from Git

Run these commands on your server:

```bash
### 1. Install dependencies
composer install --no-dev --optimize-autoloader

### 2. Run migrations (creates jobs table and Cashier tables if needed)
php artisan migrate --force

### 3. Clear and cache config
php artisan config:clear
php artisan config:cache

### 4. Restart queue worker (if using supervisor/systemd)
sudo supervisorctl restart kawhe-queue-worker:*
### OR
sudo systemctl restart kawhe-queue-worker

### 5. Test email configuration
php artisan kawhe:mail-test your-email@example.com
```

See **[PRODUCTION_EMAIL_SETUP.md](PRODUCTION_EMAIL_SETUP.md)** for complete production email setup instructions including:
- SendGrid SMTP configuration
- Queue worker setup (Supervisor/systemd)
- Monitoring and troubleshooting
- Email testing commands

#### License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
---

## RUN_PROJECT.md
<a id="appendix-runprojectmd"></a>

**Source file**: `RUN_PROJECT.md`

### Terminal Commands to Run the Project

#### Initial Setup (First Time Only)

```bash
### Navigate to project directory
cd "/Users/robertcalvert/Desktop/kawhe 2.0"

### Install PHP dependencies
composer install

### Install Node.js dependencies
npm install

### Copy environment file (if .env doesn't exist)
cp .env.example .env

### Generate application key
php artisan key:generate

### Run database migrations
php artisan migrate

### Build frontend assets
npm run build
```

#### Running the Project (Daily Use)

You need to run these commands in **separate terminal windows/tabs**:

##### Terminal 1: Laravel Server
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan serve --port=8000
```
The app will be available at: `http://localhost:8000`

##### Terminal 2: Laravel Reverb (WebSocket Server)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan reverb:start
```
This handles real-time updates for stamping and redemption.

##### Terminal 3: Frontend Assets (Development Mode)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
npm run dev
```
This runs Vite in watch mode for hot-reloading CSS/JS changes.

##### Terminal 4: ngrok (Optional - for External Access)
```bash
ngrok http 8000
```
This creates a public URL (e.g., `https://xxxxx.ngrok-free.app`) for testing on mobile devices or sharing.

**⚠️ Important for ngrok:** When accessing through ngrok, you need to **build the assets** first:
```bash
npm run build
```
This creates production-ready CSS/JS files that work through ngrok. The Vite dev server (`npm run dev`) won't work properly through ngrok URLs.

---

#### Quick Start (All-in-One)

If you want to run everything with one command (using the dev script):

```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
composer run dev
```

This runs:
- Laravel server
- Queue worker
- Pail (logs)
- Vite dev server

**Note:** You'll still need to run Reverb separately in another terminal:
```bash
php artisan reverb:start
```

---

#### Testing

```bash
### Run all tests
php artisan test

### Run specific test
php artisan test --filter=EnrollmentTest
```

---

#### Troubleshooting

If you get port conflicts:
- Change the port: `php artisan serve --port=8001`
- Update `.env` if needed: `APP_URL=http://localhost:8001`

If Reverb fails:
- Check `.env` has `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
- Make sure port 8080 is available (or change in `.env`)

**Styles missing when using ngrok?**
- Build the assets: `npm run build`
- The Vite dev server doesn't work through ngrok URLs
- After building, refresh your ngrok URL
---

## QUICK_START_LOCAL.md
<a id="appendix-quickstartlocalmd"></a>

**Source file**: `QUICK_START_LOCAL.md`

### Quick Start - Run Locally Without Errors

#### ✅ Pre-Flight Check

I've configured your app for local development. Here's what's set:

- ✅ **APP_ENV**: `local` (for development)
- ✅ **APP_DEBUG**: `true` (shows errors)
- ✅ **APP_URL**: `http://localhost:8000`
- ✅ **Migrations**: All run
- ✅ **Assets**: Built
- ✅ **Caches**: Cleared

#### 🚀 Start the App (4 Terminal Windows)

##### Terminal 1: Laravel Server
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan serve --port=8000
```
**Visit**: http://localhost:8000

##### Terminal 2: Reverb (WebSocket - for real-time updates)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan reverb:start
```
**Note**: Real-time updates will work on localhost

##### Terminal 3: Queue Worker (for emails)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan queue:work
```
**Note**: Required if emails are queued (currently set to send synchronously in local)

##### Terminal 4: Frontend Dev Server (Optional - for hot-reload)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
npm run dev
```
**Note**: Only needed if you want hot-reload. Assets are already built.

#### 📧 Email Configuration

Currently set to **log driver** (emails written to logs):
- Check emails in: `storage/logs/laravel.log`
- To switch back to SendGrid: Change `MAIL_MAILER=smtp` in `.env`

#### ✅ Verify Everything Works

1. **Visit**: http://localhost:8000
2. **Register** a merchant account
3. **Create** a store
4. **Test** the join flow
5. **Test** scanning/stamping

#### 🐛 If You See Errors

##### Database Errors
```bash
php artisan migrate
```

##### Asset Errors
```bash
npm run build
```

##### Cache Issues
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

##### Permission Errors
```bash
chmod -R 775 storage bootstrap/cache
```

#### 📝 Current Configuration

- **Environment**: Local
- **Debug Mode**: Enabled
- **Email**: Log driver (check logs)
- **WebSocket**: Enabled (works on localhost)
- **Queue**: Database (but emails send synchronously in local)

#### 🎯 Quick Test

1. Start all 4 terminals
2. Visit http://localhost:8000
3. Register → Create Store → Get Join Link
4. Open join link in new tab
5. Create customer → View card
6. Scan QR code from scanner

Everything should work without errors! 🎉
---

## QUICK_TEST_GUIDE.md
<a id="appendix-quicktestguidemd"></a>

**Source file**: `QUICK_TEST_GUIDE.md`

### Quick Test Guide - Email Verification & Reward Claiming

#### 🎯 Quick Test Steps

##### 1. Start the App
```bash
### Terminal 1
php artisan serve --port=8000

### Terminal 2 (optional)
php artisan reverb:start
```

##### 2. Create Test Customer
1. Visit: http://localhost:8000
2. Register merchant → Create store → Get join link
3. Join as customer with email: `test@example.com`

##### 3. Test Email Verification

**Option A: Use the Helper Script**
```bash
### After clicking "Verify Email" on card page
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

##### 4. Test Reward Claiming

1. **Add stamps** via scanner until reward is available
2. **Check card** - should show redeem QR (if email verified)
3. **Scan redeem QR** from scanner
4. **Verify** - stamps deducted, reward marked as redeemed

#### ✅ Verification Checklist

- [ ] Email verification banner appears on card
- [ ] "Verify Email" button works
- [ ] Email appears in logs (`storage/logs/laravel.log`)
- [ ] Verification link works
- [ ] Card shows email is verified
- [ ] Reward redeem QR appears (when reward available + email verified)
- [ ] Redemption works correctly
- [ ] Stamps deducted after redemption

#### 🔍 Check Status

```bash
### Check customer verification
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

#### 📝 Current Configuration

- **Local**: `MAIL_MAILER=log` (emails to logs)
- **Production**: `MAIL_MAILER=smtp` (SendGrid)
- **Email sends**: Synchronously in local, queued in production
- **No breaking changes**: All existing functionality preserved

#### 🚀 Production Ready

When you deploy to Digital Ocean:
- Just change `MAIL_MAILER=smtp` in production `.env`
- Configure SendGrid credentials
- Run queue worker: `php artisan queue:work`
- Everything else works the same!
---

## CHANGES_SUMMARY.md
<a id="appendix-changessummarymd"></a>

**Source file**: `CHANGES_SUMMARY.md`

### Recent Changes Summary

#### What Changed

##### 1. Data Integrity & Safety ✅
- **New Table**: `points_transactions` - Immutable ledger of all point changes
- **Idempotency**: Prevents duplicate processing with unique keys
- **Optimistic Locking**: Version column prevents race conditions
- **Database Transactions**: All operations are atomic

##### 2. Security & Fraud Mitigation ✅
- **Rate Limiting**: 
  - 10 stamps/minute per customer
  - 100 stamps/minute per store
  - 50 stamps/minute per IP
- **Logging**: User agent and IP address logged for every transaction

##### 3. UX & Reliability ✅
- **Better Error Messages**: Clear, user-friendly messages
- **Transaction History**: New section on customer card page
- **Receipt System**: Confirmation data in all responses
- **Retry Logic**: Automatic retry for failed WebSocket updates

#### How to See the Changes

##### 1. Transaction History (Customer Card)
1. Open any customer card: `/c/{public_token}`
2. Scroll down - you'll see a new "Recent Activity" section
3. Shows all stamp/redemption transactions with dates

##### 2. Better Error Messages (Scanner)
1. Go to `/scanner`
2. Try scanning the same card twice quickly
3. You'll see: "Please wait X more second(s)..." instead of generic error

##### 3. Rate Limiting
1. Try to stamp the same card 11 times in a minute
2. You'll get: "Too many stamps for this customer. Please wait a moment."

##### 4. Receipt Data
1. Scan a card from scanner
2. Check browser console or network tab
3. Response includes `receipt` object with transaction details

##### 5. Database Changes
Check your database:
```sql
-- See all transactions (ledger)
SELECT * FROM points_transactions ORDER BY created_at DESC LIMIT 10;

-- See idempotency keys
SELECT idempotency_key, type, created_at FROM stamp_events ORDER BY created_at DESC LIMIT 10;

-- See version numbers (optimistic locking)
SELECT id, stamp_count, version FROM loyalty_accounts LIMIT 5;
```

#### New API Endpoints

- `GET /api/card/{public_token}/transactions` - Get transaction history

#### Testing Checklist

- [ ] Open customer card - see "Recent Activity" section
- [ ] Scan a card - see improved success message
- [ ] Scan same card quickly - see cooldown message with countdown
- [ ] Check browser console - see transaction history loading
- [ ] Try rapid scans - see rate limiting in action
- [ ] Check database - see points_transactions table populated

#### Files Changed

- `app/Http/Controllers/ScannerController.php` - Added ledger, idempotency, better errors
- `app/Http/Controllers/CardController.php` - Added transaction history endpoint
- `app/Models/PointsTransaction.php` - New ledger model
- `app/Http/Middleware/RateLimitStamps.php` - Rate limiting middleware
- `resources/views/card/show.blade.php` - Added transaction history UI
- `resources/views/scanner/index.blade.php` - Improved feedback messages

#### Next Steps

If you don't see changes:
1. Hard refresh browser (Cmd+Shift+R / Ctrl+Shift+R)
2. Clear browser cache
3. Make sure you're accessing via `http://localhost:8000` (not ngrok for local testing)
4. Check browser console for any JavaScript errors
---

## BILLING_SETUP.md
<a id="appendix-billingsetupmd"></a>

**Source file**: `BILLING_SETUP.md`

### Stripe Billing Setup Guide

This guide covers setting up Stripe subscriptions for merchant billing in Kawhe Loyalty.

#### Overview

Kawhe Loyalty uses Laravel Cashier (Stripe) to manage merchant subscriptions:
- **Free Plan**: Up to 50 loyalty cards per merchant account
- **Pro Plan**: Unlimited loyalty cards (requires subscription)
- Merchants can upgrade/downgrade via Stripe Checkout and Billing Portal

#### Prerequisites

1. A Stripe account (sign up at https://stripe.com)
2. Access to Stripe Dashboard

#### Step 1: Configure Stripe Keys

Add these environment variables to your `.env` file:

```env
STRIPE_KEY=pk_test_...  # Your Stripe publishable key
STRIPE_SECRET=sk_test_...  # Your Stripe secret key
STRIPE_PRICE_ID=price_...  # Your subscription price ID (see Step 2)
STRIPE_WEBHOOK_SECRET=whsec_...  # Your webhook signing secret (see Step 4)
```

##### Getting Your Stripe Keys

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to **Developers** → **API keys**
3. Copy your **Publishable key** → `STRIPE_KEY`
4. Copy your **Secret key** → `STRIPE_SECRET`

**Note**: Use test keys (`pk_test_...` / `sk_test_...`) for development, and live keys (`pk_live_...` / `sk_live_...`) for production.

#### Step 2: Create Subscription Price

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to **Products** → **Add product**
3. Create a new product:
   - **Name**: "Kawhe Pro Plan" (or your preferred name)
   - **Description**: "Unlimited loyalty cards for merchants"
   - **Pricing**: Set your monthly/yearly price
   - **Billing period**: Monthly or Yearly
4. After creating, copy the **Price ID** (starts with `price_...`)
5. Add it to `.env` as `STRIPE_PRICE_ID`

#### Step 3: Run Migrations

Cashier requires database tables for subscriptions. Run migrations:

```bash
php artisan migrate
```

This creates:
- `subscriptions` table
- `subscription_items` table
- Stripe-related columns on `users` table

#### Step 4: Configure Webhook Endpoint

Stripe webhooks notify your app about subscription events (payment succeeded, cancelled, etc.).

##### For Local Development (using ngrok)

1. Start your local server:
   ```bash
   php artisan serve
   ```

2. In another terminal, expose your local server:
   ```bash
   ngrok http 8000
   ```

3. Copy the HTTPS URL (e.g., `https://abc123.ngrok-free.app`)

4. Go to [Stripe Dashboard](https://dashboard.stripe.com) → **Developers** → **Webhooks**

5. Click **Add endpoint**

6. Set:
   - **Endpoint URL**: `https://your-ngrok-url.ngrok-free.app/stripe/webhook`
   - **Events to send**: Select these events:
     - `customer.subscription.created`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`
     - `invoice.payment_succeeded`
     - `invoice.payment_failed`
     - `customer.subscription.trial_will_end`

7. Click **Add endpoint**

8. Copy the **Signing secret** (starts with `whsec_...`)

9. Add it to `.env` as `STRIPE_WEBHOOK_SECRET`

##### For Production

1. Deploy your application

2. Go to [Stripe Dashboard](https://dashboard.stripe.com) → **Developers** → **Webhooks**

3. Click **Add endpoint**

4. Set:
   - **Endpoint URL**: `https://yourdomain.com/stripe/webhook`
   - **Events to send**: Same events as above

5. Copy the **Signing secret** and add to production `.env`

#### Step 5: Test the Integration

##### Test Free Plan Limit

1. Create a merchant account
2. Create 50 loyalty cards (should work)
3. Try to create the 51st card → Should show "Limit Reached" page
4. Existing cards should still work (stamping/redeeming)

##### Test Subscription Flow

1. As a merchant, go to `/billing`
2. Click **Upgrade to Pro**
3. Complete Stripe Checkout (use test card: `4242 4242 4242 4242`)
4. After successful payment, verify:
   - Dashboard shows "Pro Plan Active"
   - Can create unlimited cards
   - `/billing` shows subscription details

##### Test Webhook

1. In Stripe Dashboard → **Developers** → **Webhooks**
2. Find your webhook endpoint
3. Click **Send test webhook**
4. Select event type (e.g., `customer.subscription.created`)
5. Verify your app receives it (check Laravel logs)

#### Environment Variables Summary

```env
### Stripe API Keys
STRIPE_KEY=pk_test_51AbC123...  # Publishable key
STRIPE_SECRET=sk_test_51XyZ789...  # Secret key

### Subscription Price
STRIPE_PRICE_ID=price_1AbC123...  # Price ID from Stripe Dashboard

### Webhook Security
STRIPE_WEBHOOK_SECRET=whsec_1XyZ789...  # Webhook signing secret
```

#### Production Deployment Checklist

After deploying to production:

1. ✅ Update `.env` with **live** Stripe keys (not test keys)
2. ✅ Create a **live** subscription price in Stripe Dashboard
3. ✅ Update `STRIPE_PRICE_ID` with live price ID
4. ✅ Configure production webhook endpoint in Stripe Dashboard
5. ✅ Update `STRIPE_WEBHOOK_SECRET` with production webhook secret
6. ✅ Run migrations: `php artisan migrate --force`
7. ✅ Clear config cache: `php artisan config:clear && php artisan config:cache`
8. ✅ Test subscription flow with a real payment method

#### Troubleshooting

##### "Stripe price ID not configured"

- Ensure `STRIPE_PRICE_ID` is set in `.env`
- Verify the price ID exists in your Stripe Dashboard
- Clear config cache: `php artisan config:clear`

##### Webhook not receiving events

- Verify webhook URL is accessible (not behind firewall)
- Check webhook signing secret matches in `.env`
- View webhook logs in Stripe Dashboard → **Developers** → **Webhooks**
- Check Laravel logs: `storage/logs/laravel.log`

##### Subscription not activating after payment

- Check webhook is configured correctly
- Verify webhook events are being sent
- Check Laravel logs for webhook processing errors
- Manually sync subscription: `php artisan cashier:webhook` (if available)

##### "Limit Reached" but merchant is subscribed

- Verify subscription status in Stripe Dashboard
- Check `subscriptions` table in database
- Ensure webhook processed subscription creation event
- Clear config cache: `php artisan config:clear`

#### Additional Resources

- [Laravel Cashier Documentation](https://laravel.com/docs/cashier)
- [Stripe API Documentation](https://stripe.com/docs/api)
- [Stripe Webhooks Guide](https://stripe.com/docs/webhooks)
---

## BILLING_IMPLEMENTATION_SUMMARY.md
<a id="appendix-billingimplementationsummarymd"></a>

**Source file**: `BILLING_IMPLEMENTATION_SUMMARY.md`

### Billing Implementation Summary

This document summarizes the monetization gate implementation using Stripe subscriptions via Laravel Cashier.

#### Overview

- **Free Plan**: Up to 50 loyalty cards per merchant account
- **Pro Plan**: Unlimited loyalty cards (requires subscription)
- Limit enforcement only applies to **new** loyalty account creation
- Existing customers can still use their cards (stamping/redeeming works)

#### Files Changed

##### 1. Package Installation
- `composer.json` - Added `laravel/cashier` dependency

##### 2. Models
- `app/Models/User.php` - Added `Billable` trait from Laravel Cashier

##### 3. Services
- `app/Services/Billing/UsageService.php` - **NEW** - Service for counting cards and checking limits
  - `cardsCountForUser(User $user): int` - Counts loyalty cards across all stores
  - `freeLimit(): int` - Returns 50
  - `isSubscribed(User $user): bool` - Checks subscription status
  - `canCreateCard(User $user): bool` - Determines if merchant can create new cards
  - `getUsageStats(User $user): array` - Returns usage statistics

##### 4. Controllers
- `app/Http/Controllers/JoinController.php` - Modified `store()` method
  - Checks if loyalty account already exists (allows existing customers)
  - Enforces limit before creating new loyalty accounts
  - Returns friendly error page if limit reached
  - Logs blocked attempts

- `app/Http/Controllers/BillingController.php` - **NEW**
  - `index()` - Billing overview page
  - `checkout()` - Creates Stripe Checkout session
  - `portal()` - Redirects to Stripe Billing Portal
  - `success()` - Success page after subscription
  - `cancel()` - Cancel page after cancelled checkout

##### 5. Routes
- `routes/web.php`
  - Added billing routes (`/billing`, `/billing/checkout`, `/billing/portal`, etc.)
  - Added Stripe webhook route (`/stripe/webhook`)
  - Updated dashboard route to pass usage stats

##### 6. Views
- `resources/views/dashboard.blade.php` - Updated
  - Shows plan status (Free/Pro)
  - Displays usage meter (X / 50 cards)
  - Shows warning banner when limit reached
  - Upgrade CTA button

- `resources/views/billing/index.blade.php` - **NEW**
  - Billing overview page
  - Current plan status
  - Usage statistics
  - Subscription details
  - Upgrade benefits

- `resources/views/billing/success.blade.php` - **NEW**
  - Success page after subscription activation

- `resources/views/billing/cancel.blade.php` - **NEW**
  - Cancel page after cancelled checkout

- `resources/views/join/limit-reached.blade.php` - **NEW**
  - Customer-facing error page when limit reached
  - Friendly message with store name
  - "Try Again Later" button

##### 7. Migrations
- `database/migrations/2026_01_13_001909_create_subscriptions_table.php` - **NEW** (from Cashier)
- `database/migrations/2026_01_13_001910_create_subscription_items_table.php` - **NEW** (from Cashier)
- Additional Cashier migrations for user table columns

##### 8. Configuration
- `config/cashier.php` - **NEW** (published from Cashier)
  - Stripe keys configuration
  - Webhook secret configuration

##### 9. Documentation
- `README.md` - Updated with billing features and links
- `BILLING_SETUP.md` - **NEW** - Complete Stripe setup guide

#### Key Implementation Details

##### Limit Enforcement Logic

1. **When**: Only enforced when creating a **new** loyalty account
2. **Where**: `JoinController::store()` method
3. **How**:
   - First checks if loyalty account already exists for customer + store
   - If exists, allows (no limit check)
   - If new, checks `UsageService::canCreateCard()`
   - If blocked, returns friendly error page
   - Logs blocked attempts for debugging

##### Usage Counting

- Counts all `loyalty_accounts` where `store_id` belongs to stores owned by the merchant
- Uses efficient query: `LoyaltyAccount::whereIn('store_id', $storeIds)->count()`
- Single query per check (no N+1 issues)

##### Subscription Management

- Uses Laravel Cashier's built-in subscription handling
- Subscription name: `'default'`
- Stripe Checkout for new subscriptions
- Stripe Billing Portal for managing/cancelling
- Webhooks automatically handled by Cashier

#### Environment Variables Required

```env
STRIPE_KEY=pk_test_...  # Stripe publishable key
STRIPE_SECRET=sk_test_...  # Stripe secret key
STRIPE_PRICE_ID=price_...  # Subscription price ID
STRIPE_WEBHOOK_SECRET=whsec_...  # Webhook signing secret
```

#### Artisan Commands After Deploy

```bash
### 1. Run migrations (creates Cashier tables)
php artisan migrate --force

### 2. Clear and cache config
php artisan config:clear
php artisan config:cache
```

#### Testing Checklist

##### Free Merchant Under Limit
- [ ] Create store
- [ ] Create multiple customer joins (should work)
- [ ] Verify dashboard shows usage meter

##### Free Merchant At Limit
- [ ] Create 50 loyalty accounts
- [ ] Attempt 51st join → Should show "Limit Reached" page
- [ ] Existing customer re-joining same store → Should work
- [ ] Merchant scanner stamping/redeem → Should work for existing cards

##### Subscribed Merchant
- [ ] Subscribe via `/billing`
- [ ] Complete Stripe Checkout
- [ ] Verify dashboard shows "Pro Plan Active"
- [ ] Create customer join beyond 50 → Should work
- [ ] Verify unlimited cards

##### Webhook Testing
- [ ] Configure webhook endpoint in Stripe Dashboard
- [ ] Send test webhook from Stripe
- [ ] Verify subscription status updates in database

#### Important Notes

1. **No Breaking Changes**: All existing flows (joining, stamping, redeeming, Reverb) remain unchanged
2. **Backward Compatible**: Existing merchants and customers unaffected
3. **Production Safe**: Proper error handling, logging, and user-friendly messages
4. **Efficient**: Single query for usage counting, no N+1 issues
5. **Secure**: Webhook signature verification via Cashier middleware

#### Next Steps

1. Set up Stripe account and get API keys
2. Create subscription price in Stripe Dashboard
3. Configure webhook endpoint
4. Test the flow end-to-end
5. Deploy to production with live Stripe keys

See `BILLING_SETUP.md` for detailed setup instructions.
---

## STRIPE_SYNC_IMPLEMENTATION.md
<a id="appendix-stripesyncimplementationmd"></a>

**Source file**: `STRIPE_SYNC_IMPLEMENTATION.md`

### Stripe Subscription Sync - Implementation Summary

This document summarizes the changes made to fix subscription sync after Stripe Checkout payment.

#### Problem

After completing Stripe Checkout payment, the app was not detecting the subscription because:
1. Success URL didn't include `session_id` parameter
2. Success handler wasn't retrieving checkout session from Stripe
3. Subscription wasn't being synced to database immediately
4. Webhook might not process in time (especially for async payments)

#### Solution

Implemented reliable subscription sync with multiple fallback mechanisms:
1. **Immediate sync on success page** - Retrieves checkout session and syncs subscription
2. **Webhook sync** - Handles async payments and subscription updates
3. **Manual sync** - Allows users to manually trigger sync if needed

#### Files Modified

##### 1. `app/Http/Controllers/BillingController.php`

**Changes:**
- Updated `checkout()` method to include `session_id` in success URL
- Added `client_reference_id` to checkout session (user ID for user lookup)
- Completely rewrote `success()` method to:
  - Retrieve checkout session from Stripe using `session_id`
  - Find user by `client_reference_id`, Stripe customer ID, or email
  - Sync subscription immediately after payment
  - Handle async payments gracefully
  - Provide clear error messages and retry options
- Added new `sync()` method for manual subscription sync (idempotent)

**Key Code Snippets:**

```php
// Checkout with session_id in success URL
$checkout = $user->newSubscription('default', $priceId)
    ->checkout([
        'success_url' => $appUrl . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => route('billing.cancel'),
        'client_reference_id' => (string) $user->id,
    ]);

// Success handler retrieves session
$session = StripeCheckoutSession::retrieve([
    'id' => $sessionId,
    'expand' => ['subscription', 'customer', 'line_items'],
]);

// Sync subscription
if ($session->subscription) {
    if (!$user->hasStripeId()) {
        $user->stripe_id = $session->customer;
        $user->save();
    }
    $user->syncStripeSubscriptions();
}
```

##### 2. `routes/web.php`

**Changes:**
- Added `POST /billing/sync` route for manual sync

```php
Route::post('/billing/sync', [App\Http\Controllers\BillingController::class, 'sync'])->name('billing.sync');
```

##### 3. `bootstrap/app.php`

**Changes:**
- Excluded Stripe webhook from CSRF verification

```php
$middleware->validateCsrfTokens(except: [
    'stripe/webhook',
]);
```

##### 4. `config/services.php`

**Changes:**
- Added Stripe configuration section

```php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

##### 5. `resources/views/billing/success.blade.php`

**Changes:**
- Updated to handle multiple states:
  - Success (subscription activated)
  - Processing (async payment)
  - Error (sync failed)
- Added manual sync button with session_id
- Improved user messaging

#### Webhook Handling

Cashier's built-in `WebhookController` already handles:
- `checkout.session.completed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`

The webhook is automatically processed by Cashier and updates the database. Our changes ensure:
1. Webhook route is excluded from CSRF
2. Webhook signature is verified (via Cashier middleware)
3. Events are logged for debugging

#### Flow Diagram

```
User clicks "Upgrade to Pro"
    ↓
Stripe Checkout (with client_reference_id)
    ↓
Payment completed
    ↓
Redirect to /billing/success?session_id=cs_...
    ↓
Success handler:
  1. Retrieve checkout session from Stripe
  2. Find user (by client_reference_id, customer_id, or email)
  3. Sync subscription to database
  4. Redirect to dashboard with success message
    ↓
OR (if async payment):
  Show "Processing" message with sync button
    ↓
Webhook arrives (later):
  Cashier processes webhook
  Updates subscription in database
```

#### Testing

See `STRIPE_SYNC_TEST_CHECKLIST.md` for detailed test steps.

#### Deployment

See `STRIPE_DEPLOYMENT.md` for production deployment instructions.

#### Key Features

1. **Reliable Sync**: Multiple mechanisms ensure subscription is detected
2. **User-Friendly**: Clear messages and retry options
3. **Non-Breaking**: All existing functionality preserved
4. **Idempotent**: Safe to retry sync multiple times
5. **Logged**: Comprehensive logging for debugging

#### Environment Variables Required

```env
STRIPE_KEY=pk_test_... or pk_live_...
STRIPE_SECRET=sk_test_... or sk_live_...
STRIPE_PRICE_ID=price_...
STRIPE_WEBHOOK_SECRET=whsec_...
APP_URL=http://localhost:8000 or https://yourdomain.com
```

#### Troubleshooting

##### Subscription not syncing

1. Check `APP_URL` matches actual URL
2. Verify `session_id` is in success URL
3. Check Laravel logs for errors
4. Try manual sync: `POST /billing/sync` with `session_id`
5. Use artisan command: `php artisan kawhe:sync-subscriptions {user_id}`

##### Webhook issues

1. Verify webhook URL is accessible
2. Check `STRIPE_WEBHOOK_SECRET` matches Stripe Dashboard
3. Verify CSRF exclusion in `bootstrap/app.php`
4. Check webhook events in Stripe Dashboard
---

## STRIPE_SYNC_CHANGES_SUMMARY.md
<a id="appendix-stripesyncchangessummarymd"></a>

**Source file**: `STRIPE_SYNC_CHANGES_SUMMARY.md`

### Stripe Subscription Sync - Complete Changes Summary

#### Files Modified

##### 1. `app/Http/Controllers/BillingController.php`

**Line 69-107: Updated `checkout()` method**
- Changed success_url to include `{CHECKOUT_SESSION_ID}` placeholder
- Added `client_reference_id` to checkout session for user lookup
- Uses `config('app.url')` for absolute URL

**Line 145-280: Completely rewrote `success()` method**
- Retrieves checkout session from Stripe using `session_id` query parameter
- Finds user by `client_reference_id`, Stripe customer ID, or email
- Syncs subscription immediately after payment
- Handles async payments (Klarna, etc.)
- Provides clear error messages and retry options
- Logs all operations for debugging

**Line 282-330: Added new `sync()` method**
- Manual sync endpoint (idempotent)
- Accepts `session_id` via POST
- Verifies session belongs to authenticated user
- Syncs subscription and returns JSON or redirect

**Line 3-5: Added imports**
```php
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeCheckoutSession;
```

##### 2. `routes/web.php`

**Line 94: Added sync route**
```php
Route::post('/billing/sync', [App\Http\Controllers\BillingController::class, 'sync'])->name('billing.sync');
```

##### 3. `bootstrap/app.php`

**Line 14-20: Added CSRF exclusion**
```php
$middleware->validateCsrfTokens(except: [
    'stripe/webhook',
]);
```

##### 4. `config/services.php`

**Line 38-42: Added Stripe config**
```php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

##### 5. `resources/views/billing/success.blade.php`

**Complete rewrite:**
- Handles multiple states: success, processing, error
- Shows appropriate icons and messages
- Includes manual sync button with session_id
- Provides links to billing page and dashboard

#### Exact Code Changes

##### Change 1: Checkout Success URL

**Before:**
```php
'success_url' => route('billing.success'),
```

**After:**
```php
$appUrl = config('app.url');
$checkout = $user->newSubscription('default', $priceId)
    ->checkout([
        'success_url' => $appUrl . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => route('billing.cancel'),
        'client_reference_id' => (string) $user->id,
    ]);
```

##### Change 2: Success Handler

**Before:**
```php
public function success(Request $request)
{
    return view('billing.success');
}
```

**After:**
```php
public function success(Request $request)
{
    $sessionId = $request->query('session_id');
    
    if (!$sessionId) {
        // Handle missing session_id
        return view('billing.success', [
            'error' => 'No session ID provided...',
            'hasSession' => false,
        ]);
    }
    
    try {
        Stripe::setApiKey(config('cashier.secret'));
        $session = StripeCheckoutSession::retrieve([
            'id' => $sessionId,
            'expand' => ['subscription', 'customer', 'line_items'],
        ]);
        
        // Find user and sync subscription
        // ... (see full implementation in file)
        
    } catch (\Exception $e) {
        // Error handling
    }
}
```

##### Change 3: CSRF Exclusion

**Before:**
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
    // ... aliases
})
```

**After:**
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
    // ... aliases
    
    $middleware->validateCsrfTokens(except: [
        'stripe/webhook',
    ]);
})
```

#### Local Test Checklist

See `STRIPE_SYNC_TEST_CHECKLIST.md` for complete test steps.

**Quick Test:**
1. Start server: `php artisan serve`
2. Go to `/billing`, click "Upgrade to Pro"
3. Complete payment with test card `4242 4242 4242 4242`
4. Verify redirect includes `?session_id=cs_test_...`
5. Verify subscription syncs and dashboard shows "Pro Plan Active"

#### Server Deploy Commands

```bash
### 1. Pull code
git pull origin main

### 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

### 3. Run migrations
php artisan migrate --force

### 4. Clear and cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

### 5. Restart services
sudo systemctl restart php-fpm
sudo systemctl restart nginx
sudo supervisorctl restart kawhe-queue-worker:*
```

#### Stripe Dashboard Setup

1. **Webhook Endpoint:**
   - URL: `https://yourdomain.com/stripe/webhook`
   - Events: `checkout.session.completed`, `customer.subscription.*`, `checkout.session.async_payment_*`
   - Copy signing secret to `.env` as `STRIPE_WEBHOOK_SECRET`

2. **Verify Price ID:**
   - Products → Your Pro Plan → Copy Price ID
   - Ensure matches `STRIPE_PRICE_ID` in `.env`

#### Non-Breaking Verification

All existing functionality preserved:
- ✅ Stamping works (`POST /stamp`)
- ✅ Redeeming works (`POST /redeem`)
- ✅ Reverb WebSocket updates work
- ✅ Store management works
- ✅ Customer join flow works
- ✅ Scanner page works

#### Key Improvements

1. **Immediate Sync**: Subscription syncs right after payment (no waiting for webhook)
2. **Fallback Mechanisms**: Manual sync if automatic sync fails
3. **Async Payment Support**: Handles Klarna and other async methods
4. **Better UX**: Clear messages and retry options
5. **Comprehensive Logging**: All operations logged for debugging
6. **Idempotent**: Safe to retry sync multiple times

#### Next Steps

1. Test locally using `STRIPE_SYNC_TEST_CHECKLIST.md`
2. Deploy to production using `STRIPE_DEPLOYMENT.md`
3. Configure Stripe webhook in Dashboard
4. Monitor logs for any issues
5. Test with real payment (small amount)
---

## STRIPE_SYNC_TEST_CHECKLIST.md
<a id="appendix-stripesynctestchecklistmd"></a>

**Source file**: `STRIPE_SYNC_TEST_CHECKLIST.md`

### Stripe Subscription Sync - Local Test Checklist

This checklist verifies that subscription sync works correctly after Stripe Checkout payment.

#### Prerequisites

Before testing, ensure your `.env` has:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_PRICE_ID=price_...
STRIPE_WEBHOOK_SECRET=whsec_...  # From Stripe CLI or Dashboard
APP_URL=http://localhost:8000  # Or your ngrok URL
```

#### Test Steps

##### 1. Start Local Environment

- [ ] Start Laravel server: `php artisan serve`
- [ ] (Optional) Start Stripe CLI: `stripe listen --forward-to localhost:8000/stripe/webhook`
- [ ] Copy webhook secret from Stripe CLI output to `.env` if using CLI
- [ ] Clear config cache: `php artisan config:clear`

##### 2. Pre-Checkout Verification

- [ ] Log in as a merchant user
- [ ] Navigate to `/billing`
- [ ] Verify "Free Plan" is shown
- [ ] Verify usage meter shows current card count (e.g., "X / 50")
- [ ] Click "Upgrade to Pro" button
- [ ] Verify redirect to Stripe Checkout page

##### 3. Complete Payment

- [ ] On Stripe Checkout page, use test card: `4242 4242 4242 4242`
- [ ] Enter any future expiry date (e.g., 12/34)
- [ ] Enter any CVC (e.g., 123)
- [ ] Enter any ZIP code
- [ ] Click "Subscribe" or "Pay"
- [ ] **Verify URL includes `?session_id=cs_test_...`** in browser address bar after redirect

##### 4. Success Page Verification

- [ ] After payment, verify redirect to `/billing/success?session_id=...`
- [ ] Check for one of these outcomes:
  - ✅ **Best case**: Redirected to dashboard with success message "Pro Plan Active"
  - ✅ **Good case**: Success page shows "Subscription Activating" with sync button
  - ⚠️ **Fallback**: Success page shows error message (should still have sync option)

##### 5. Database Verification

- [ ] Check `users` table:
  ```sql
  SELECT id, email, stripe_id FROM users WHERE id = {your_user_id};
  ```
  - Verify `stripe_id` is set (should be `cus_...`)

- [ ] Check `subscriptions` table:
  ```sql
  SELECT * FROM subscriptions WHERE user_id = {your_user_id};
  ```
  - Verify subscription exists
  - Verify `stripe_status` is `active` or `trialing`
  - Verify `type` is `default`

##### 6. Dashboard Verification

- [ ] Navigate to `/merchant/dashboard`
- [ ] Verify "Pro Plan Active" is shown (green checkmark)
- [ ] Verify usage meter shows "Cards issued: X / ∞" (unlimited)
- [ ] Verify "Upgrade" button is **NOT** visible
- [ ] Verify no "Limit Reached" warning banner

##### 7. Billing Page Verification

- [ ] Navigate to `/billing`
- [ ] Verify "Pro Plan Active" is shown
- [ ] Verify "Manage Subscription" button is visible
- [ ] Verify subscription details show status: `active` or `trialing`

##### 8. Manual Sync Test (if needed)

- [ ] If subscription not detected, click "Sync Subscription Status" on billing page
- [ ] Or use artisan command: `php artisan kawhe:sync-subscriptions {user_id}`
- [ ] Verify subscription syncs correctly
- [ ] Re-check dashboard and billing page

##### 9. Webhook Verification (if using Stripe CLI)

- [ ] Check Stripe CLI output for webhook events:
  - `checkout.session.completed`
  - `customer.subscription.created`
  - `customer.subscription.updated`
- [ ] Check Laravel logs: `tail -f storage/logs/laravel.log`
- [ ] Verify webhook processing logs appear
- [ ] Verify no webhook errors

##### 10. Functional Verification (Non-Breaking)

- [ ] **Stamping still works**: Go to scanner, scan a card, verify stamp is added
- [ ] **Redeeming still works**: Redeem a reward, verify it works
- [ ] **Reverb still works**: Open card page, stamp from scanner, verify real-time update
- [ ] **Store management still works**: Create/edit stores, verify no errors
- [ ] **Customer join still works**: Create new loyalty account, verify it works (should be unlimited now)

##### 11. Edge Cases

- [ ] Test with missing `session_id`: Go to `/billing/success` directly
  - Should show friendly error message
  - Should have link back to billing page

- [ ] Test manual sync endpoint: `POST /billing/sync` with `session_id`
  - Should sync subscription
  - Should be idempotent (safe to call multiple times)

- [ ] Test async payment (if Klarna enabled):
  - Use Klarna payment method
  - Verify success page shows "Payment Processing" message
  - Verify sync button is available
  - Wait for webhook, verify subscription activates

#### Expected Results

✅ **All tests should pass**

- Subscription syncs immediately after payment (best case)
- Or syncs within a few seconds via webhook
- Or can be manually synced via button/command
- Dashboard shows "Pro Plan Active"
- All existing functionality (stamping, redeeming, Reverb) still works
- No errors in logs

#### Troubleshooting

##### Subscription not syncing

1. Check `.env` has correct `STRIPE_SECRET`
2. Check Laravel logs for errors
3. Verify `APP_URL` matches your actual URL
4. Try manual sync: `php artisan kawhe:sync-subscriptions {user_id}`
5. Check Stripe Dashboard → Customers → verify customer exists
6. Check Stripe Dashboard → Subscriptions → verify subscription exists

##### Webhook not working

1. Verify Stripe CLI is running (if testing locally)
2. Check webhook URL is correct: `http://localhost:8000/stripe/webhook`
3. Verify `STRIPE_WEBHOOK_SECRET` matches Stripe CLI output
4. Check `bootstrap/app.php` excludes webhook from CSRF
5. Check Laravel logs for webhook processing errors

##### CSRF errors

1. Verify `bootstrap/app.php` has webhook excluded:
   ```php
   $middleware->validateCsrfTokens(except: ['stripe/webhook']);
   ```
2. Clear config cache: `php artisan config:clear`
---

## STRIPE_DEPLOYMENT.md
<a id="appendix-stripedeploymentmd"></a>

**Source file**: `STRIPE_DEPLOYMENT.md`

### Stripe Subscription Sync - Production Deployment Guide

This guide covers deploying the Stripe subscription sync fixes to DigitalOcean.

#### Pre-Deployment Checklist

- [ ] All local tests pass (see `STRIPE_SYNC_TEST_CHECKLIST.md`)
- [ ] Code committed and pushed to Git
- [ ] Stripe account has live API keys (not test keys)
- [ ] Subscription price created in Stripe Dashboard (live mode)
- [ ] Webhook endpoint URL ready (e.g., `https://yourdomain.com/stripe/webhook`)

#### Server Deployment Steps

##### 1. SSH into DigitalOcean Server

```bash
ssh user@your-server-ip
cd /path/to/kawhe-loyalty
```

##### 2. Pull Latest Code

```bash
git pull origin main
### Or your branch name
```

##### 3. Install Dependencies

```bash
### PHP dependencies
composer install --no-dev --optimize-autoloader

### Frontend assets (if changed)
npm ci
npm run build
```

##### 4. Run Migrations

```bash
php artisan migrate --force
```

This ensures all Cashier tables exist.

##### 5. Clear and Cache Configuration ⚠️ CRITICAL

**IMPORTANT:** The new `price_id` config key must be cached. If you skip this step, `STRIPE_PRICE_ID` will show as "Not set" even if it's in your `.env` file.

```bash
### Clear old config (removes cached config that doesn't have price_id)
php artisan config:clear
php artisan route:clear
php artisan view:clear

### Cache for production (includes new price_id from config/cashier.php)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Verify config is cached correctly:**
```bash
php artisan tinker
>>> config('cashier.price_id')
### Should return your STRIPE_PRICE_ID value, not null
```

##### 6. Update Environment Variables

Edit `.env` file on server:

```env
### Stripe Live Keys (NOT test keys)
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_PRICE_ID=price_...  # Live price ID from Stripe Dashboard
STRIPE_WEBHOOK_SECRET=whsec_...  # From Stripe Dashboard webhook (see below)

### App URL (your production domain)
APP_URL=https://yourdomain.com
APP_ENV=production
```

**⚠️ After updating .env, you MUST clear and recache config:**
```bash
php artisan config:clear
php artisan config:cache
```

This is critical because the new `price_id` config key must be in the cached config.

##### 7. Restart PHP-FPM (if applicable)

```bash
### For PHP-FPM
sudo systemctl restart php8.2-fpm
### Or
sudo service php-fpm restart

### For Nginx
sudo systemctl restart nginx
```

##### 8. Restart Queue Workers (if using queues)

```bash
### If using Supervisor
sudo supervisorctl restart kawhe-queue-worker:*

### If using systemd
sudo systemctl restart kawhe-queue-worker

### Or manually
php artisan queue:restart
```

##### 9. Verify Application

```bash
### Check application is running
curl https://yourdomain.com/up

### Check routes are cached
php artisan route:list | grep billing
php artisan route:list | grep stripe
```

#### Stripe Dashboard Configuration

##### 1. Create/Update Webhook Endpoint

1. Go to [Stripe Dashboard](https://dashboard.stripe.com) → **Developers** → **Webhooks**
2. Click **Add endpoint** (or edit existing)
3. Set **Endpoint URL**: `https://yourdomain.com/stripe/webhook`
4. Select **Events to send**:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `checkout.session.async_payment_succeeded` (if using Klarna)
   - `checkout.session.async_payment_failed` (if using Klarna)
5. Click **Add endpoint**
6. **Copy the Signing secret** (starts with `whsec_...`)
7. Add to server `.env` as `STRIPE_WEBHOOK_SECRET`

##### 2. Test Webhook

1. In Stripe Dashboard → **Developers** → **Webhooks**
2. Find your webhook endpoint
3. Click **Send test webhook**
4. Select event: `checkout.session.completed`
5. Click **Send test webhook**
6. Check server logs: `tail -f storage/logs/laravel.log`
7. Verify webhook is received and processed (200 response)

##### 3. Verify Subscription Price

1. Go to **Products** → Find your Pro Plan product
2. Verify **Price ID** matches `STRIPE_PRICE_ID` in `.env`
3. Verify it's in **Live mode** (not test mode)

#### Post-Deployment Verification

##### 1. Test Subscription Flow

- [ ] Go to `https://yourdomain.com/billing`
- [ ] Click "Upgrade to Pro"
- [ ] Complete payment with real card (or test card in test mode)
- [ ] Verify redirect to success page with `session_id`
- [ ] Verify subscription syncs and dashboard shows "Pro Plan Active"

##### 2. Check Logs

```bash
### Watch logs in real-time
tail -f storage/logs/laravel.log

### Look for:
### - "Checkout session retrieved"
### - "Subscription synced after checkout"
### - Webhook processing logs
### - Any errors
```

##### 3. Verify Database

```bash
### Connect to database
php artisan tinker

### Check subscription
$user = User::find({user_id});
$user->subscription('default');
$user->subscribed('default');  // Should return true
```

##### 4. Functional Tests

- [ ] Stamping works: Scanner → scan card → verify stamp added
- [ ] Redeeming works: Redeem reward → verify it works
- [ ] Reverb works: Real-time updates still function
- [ ] Store management works: Create/edit stores
- [ ] Customer join works: New customers can join (unlimited for Pro)

#### Troubleshooting

##### Subscription Not Syncing After Payment

1. **Check success URL includes session_id**:
   - Payment should redirect to `/billing/success?session_id=cs_...`
   - If missing, check `APP_URL` in `.env`

2. **Check Stripe API keys**:
   - Verify `STRIPE_SECRET` is correct (live key for production)
   - Test with: `php artisan tinker` → `\Stripe\Stripe::setApiKey(config('cashier.secret'));`

3. **Check logs for errors**:
   ```bash
   tail -f storage/logs/laravel.log | grep -i "stripe\|subscription\|checkout"
   ```

4. **Manual sync**:
   ```bash
   php artisan kawhe:sync-subscriptions {user_id}
   ```

##### Webhook Not Receiving Events

1. **Verify webhook URL is accessible**:
   ```bash
   curl -X POST https://yourdomain.com/stripe/webhook
   # Should return 200 or 400 (not 404)
   ```

2. **Check CSRF exclusion**:
   - Verify `bootstrap/app.php` has: `$middleware->validateCsrfTokens(except: ['stripe/webhook']);`
   - Clear config cache: `php artisan config:clear && php artisan config:cache`

3. **Check webhook secret**:
   - Verify `STRIPE_WEBHOOK_SECRET` matches Stripe Dashboard
   - Clear config cache after updating

4. **Check Stripe Dashboard**:
   - Go to **Developers** → **Webhooks**
   - Click on your endpoint
   - Check **Recent events** tab
   - Verify events are being sent
   - Check for failed deliveries

##### CSRF Errors on Webhook

1. **Verify exclusion in bootstrap/app.php**:
   ```php
   $middleware->validateCsrfTokens(except: ['stripe/webhook']);
   ```

2. **Clear all caches**:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   php artisan config:cache
   php artisan route:cache
   ```

##### Subscription Shows But Dashboard Still Shows Free Plan

1. **Clear application cache**:
   ```bash
   php artisan cache:clear
   ```

2. **Check UsageService logic**:
   - Verify `isSubscribed()` method checks subscription status correctly
   - Check `subscriptions` table has correct `stripe_status`

3. **Manual refresh**:
   - Go to `/billing?refresh=1`
   - Or use: `php artisan kawhe:sync-subscriptions {user_id}`

#### Monitoring

##### Recommended Monitoring

1. **Log Monitoring**:
   - Set up log rotation: `logrotate` for `storage/logs/laravel.log`
   - Monitor for Stripe/webhook errors

2. **Queue Monitoring** (if using queues):
   - Monitor queue:work process
   - Set up alerts for failed jobs

3. **Stripe Dashboard**:
   - Monitor webhook delivery success rate
   - Set up alerts for failed webhooks

#### Rollback Plan

If issues occur:

1. **Revert code**:
   ```bash
   git checkout {previous-commit}
   git pull
   composer install --no-dev
   php artisan config:clear
   php artisan config:cache
   ```

2. **Clear caches**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

3. **Restart services**:
   ```bash
   sudo systemctl restart php-fpm
   sudo systemctl restart nginx
   ```

#### Support Contacts

- **Stripe Support**: https://support.stripe.com
- **Laravel Cashier Docs**: https://laravel.com/docs/cashier
- **Server Issues**: Contact your DigitalOcean support
---

## GRANDFATHERING_IMPLEMENTATION.md
<a id="appendix-grandfatheringimplementationmd"></a>

**Source file**: `GRANDFATHERING_IMPLEMENTATION.md`

### Grandfathering Implementation - Subscription Cancellation Handling

#### Overview

This document describes the implementation of **grandfathering** for loyalty cards when a merchant cancels their Pro subscription. Cards created during the Pro subscription period remain active (grandfathered), while new card creation is limited to the free plan limit (50 cards).

#### Implementation Details

##### Core Logic

1. **Grandfathered Cards**: Cards created **before** subscription cancellation (`ends_at` date) remain active forever
2. **Non-Grandfathered Cards**: Cards created **after** subscription cancellation count toward the 50-card free limit
3. **Active Subscription**: All cards work, unlimited creation
4. **Cancelled Subscription**: Grandfathered cards work, but new creation limited to 50 non-grandfathered cards

##### Key Changes

#### 1. `UsageService` Updates

**New Methods:**
- `cardsCountForUser($user, $includeGrandfathered = true)`: Counts cards, optionally excluding grandfathered ones
- `grandfatheredCardsCount($user)`: Returns count of grandfathered cards

**Updated Methods:**
- `canCreateCard($user)`: Now checks only non-grandfathered cards against the limit
- `getUsageStats($user)`: Returns additional stats:
  - `non_grandfathered_count`: Cards created after cancellation
  - `grandfathered_count`: Cards created before cancellation
  - `has_cancelled_subscription`: Boolean flag

**Logic:**
```php
// If subscription cancelled (has ends_at), only count cards created AFTER ends_at
if ($subscription && $subscription->ends_at) {
    $query->where('created_at', '>=', $subscription->ends_at);
}
```

#### 2. UI Updates

**Dashboard (`dashboard.blade.php`):**
- Shows grandfathered count in card display
- Displays warning banner for cancelled subscriptions with grandfathered cards
- Progress bar uses non-grandfathered count

**Billing Page (`billing/index.blade.php`):**
- Shows grandfathered count
- Usage stats reflect non-grandfathered cards
- Clear messaging about grandfathered cards

**Profile Page (`profile/partials/subscription-details.blade.php`):**
- Shows grandfathered count
- Displays info message about grandfathered cards
- Limit checks use non-grandfathered count

#### 3. JoinController Updates

- Updated logging to include grandfathered vs non-grandfathered counts
- Limit enforcement uses `canCreateCard()` which respects grandfathering

##### Webhook Handling

Laravel Cashier's `WebhookController` automatically handles subscription cancellation:

1. **`customer.subscription.updated`**: Updates subscription status and `ends_at` when cancelled
2. **`customer.subscription.deleted`**: Marks subscription as deleted

The `ends_at` field is automatically set by Cashier when:
- Subscription is cancelled (immediate or at period end)
- Subscription expires

Our `isSubscribed()` method correctly returns `false` for cancelled subscriptions (checks for 'active' or 'trialing' status only).

##### Example Scenarios

#### Scenario 1: Merchant with 100 cards cancels subscription
- **Before cancellation**: 100 cards, all active, unlimited creation
- **After cancellation**: 
  - 100 cards remain active (all grandfathered)
  - Cannot create new cards (non-grandfathered count = 0, but limit is 50)
  - Wait, this is wrong... Let me recalculate

Actually, if they have 100 cards and cancel:
- All 100 cards created BEFORE cancellation → all grandfathered
- Non-grandfathered count = 0
- Can create up to 50 new cards (0 < 50)

#### Scenario 2: Merchant cancels, then creates 60 new cards
- **After cancellation**: 0 grandfathered, 0 non-grandfathered
- **Creates 50 cards**: 0 grandfathered, 50 non-grandfathered (at limit)
- **Tries to create 51st**: Blocked (50 >= 50)
- **Grandfathered cards from before**: Still work (if any existed)

#### Scenario 3: Merchant resubscribes
- All cards work (grandfathered + non-grandfathered)
- Unlimited creation restored
- Grandfathered status becomes irrelevant (all cards work)

##### Testing Checklist

- [ ] Merchant with active subscription can create unlimited cards
- [ ] Merchant cancels subscription → `ends_at` is set
- [ ] Cards created before cancellation remain active (grandfathered)
- [ ] Cards created after cancellation count toward 50 limit
- [ ] Cannot create new cards if non-grandfathered count >= 50
- [ ] Can create new cards if non-grandfathered count < 50
- [ ] Dashboard shows grandfathered count
- [ ] Billing page shows correct usage stats
- [ ] Profile page shows grandfathered info
- [ ] Resubscribing removes restrictions

##### Database Schema

No new migrations required. Uses existing fields:
- `subscriptions.ends_at`: Set by Cashier when subscription cancelled
- `loyalty_accounts.created_at`: Used to determine if card is grandfathered

##### Backward Compatibility

✅ **Fully backward compatible:**
- Existing functionality unchanged
- No breaking changes to API
- All existing cards continue to work
- Only affects new card creation logic

##### Future Considerations

1. **Resubscription**: When merchant resubscribes, all cards work (no need to track grandfathering)
2. **Multiple Cancellations**: If merchant cancels and resubscribes multiple times, only the most recent `ends_at` matters
3. **Edge Cases**: 
   - What if subscription is cancelled but then reactivated before `ends_at`? → `isSubscribed()` returns true, all cards work
   - What if `ends_at` is in the future? → Cards created now are grandfathered (created before ends_at)

#### Files Modified

1. `app/Services/Billing/UsageService.php` - Core grandfathering logic
2. `app/Http/Controllers/JoinController.php` - Updated logging
3. `resources/views/dashboard.blade.php` - UI updates
4. `resources/views/billing/index.blade.php` - UI updates
5. `resources/views/profile/partials/subscription-details.blade.php` - UI updates

#### Notes

- Grandfathering is automatic based on `created_at` vs `ends_at` comparison
- No manual intervention needed
- Webhook handling is automatic via Cashier
- All existing functionality preserved
---

## SENDGRID_SETUP.md
<a id="appendix-sendgridsetupmd"></a>

**Source file**: `SENDGRID_SETUP.md`

### SendGrid SMTP Setup Guide

#### ✅ What's Already Configured

1. **Mail Configuration** - Laravel mail config is set up for SMTP
2. **Email Mailable** - `VerifyCustomerEmail` class is ready
3. **Queue System** - Emails are queued (using `Mail::queue()`)
4. **Jobs Table Migration** - Database queue tables exist

#### 🔧 What You Need to Complete

##### Step 1: Get Your SendGrid API Key

1. Sign up/Login to [SendGrid](https://sendgrid.com/)
2. Go to **Settings** → **API Keys**
3. Click **Create API Key**
4. Name it (e.g., "Kawhe Loyalty App")
5. Select **Full Access** or **Restricted Access** (with Mail Send permissions)
6. Copy the API key (you'll only see it once!)

##### Step 2: Verify Your Sender Email

1. In SendGrid, go to **Settings** → **Sender Authentication**
2. Click **Verify a Single Sender** (for testing) or **Authenticate Your Domain** (for production)
3. Follow the verification steps
4. Note the verified email address (e.g., `noreply@yourdomain.com`)

##### Step 3: Update Your `.env` File

Add these lines to your `.env` file:

```env
### Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_actual_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

### Queue Configuration (for processing emails)
QUEUE_CONNECTION=database
```

**Important:**
- Replace `SG.your_actual_sendgrid_api_key_here` with your actual SendGrid API key
- Replace `noreply@yourdomain.com` with your verified sender email
- The `MAIL_USERNAME` must be exactly `apikey` (this is SendGrid's requirement)

##### Step 4: Run Database Migrations (if not done)

```bash
php artisan migrate
```

This creates the `jobs` and `failed_jobs` tables needed for queued emails.

##### Step 5: Start the Queue Worker

Since emails are queued, you need to run a queue worker to process them:

```bash
php artisan queue:work
```

Or for development with auto-restart:
```bash
php artisan queue:listen
```

**Note:** Keep this running in a separate terminal window while your app is running.

---

#### 🧪 Testing the Setup

##### Test 1: Send a Test Email

You can test by:
1. Going to a customer card page
2. Clicking "Verify Email" 
3. Check the queue worker terminal for processing
4. Check your email inbox

##### Test 2: Check Queue Status

```bash
### See pending jobs
php artisan queue:monitor

### See failed jobs
php artisan queue:failed
```

##### Test 3: Check Logs

If emails aren't sending, check:
```bash
### Laravel logs
tail -f storage/logs/laravel.log

### Or use Pail
php artisan pail
```

---

#### 🚨 Troubleshooting

##### Emails Not Sending?

1. **Check Queue Worker is Running**
   - Make sure `php artisan queue:work` is running
   - Emails won't send if the queue worker isn't processing jobs

2. **Verify .env Settings**
   - Make sure `MAIL_USERNAME=apikey` (literally the word "apikey")
   - Verify your API key starts with `SG.`
   - Check `MAIL_FROM_ADDRESS` matches your verified sender

3. **Check SendGrid Dashboard**
   - Go to SendGrid → Activity
   - See if emails are being received/rejected
   - Check for bounce or spam reports

4. **Test SMTP Connection**
   ```bash
   php artisan tinker
   ```
   Then in tinker:
   ```php
   Mail::raw('Test email', function($message) {
       $message->to('your-email@example.com')
               ->subject('Test');
   });
   ```

##### Queue Jobs Failing?

```bash
### See failed jobs
php artisan queue:failed

### Retry failed jobs
php artisan queue:retry all

### Clear failed jobs
php artisan queue:flush
```

---

#### 📝 Production Recommendations

1. **Use Supervisor** (Linux) or **Laravel Horizon** for queue management
2. **Set up email monitoring** in SendGrid
3. **Use domain authentication** instead of single sender verification
4. **Set up webhooks** for bounce/spam handling
5. **Monitor queue:failed** table regularly

---

#### 🔗 Quick Reference

- **SendGrid Dashboard:** https://app.sendgrid.com/
- **API Keys:** https://app.sendgrid.com/settings/api_keys
- **Sender Verification:** https://app.sendgrid.com/settings/sender_auth
- **Activity Feed:** https://app.sendgrid.com/activity
---

## SENDGRID_TROUBLESHOOTING.md
<a id="appendix-sendgridtroubleshootingmd"></a>

**Source file**: `SENDGRID_TROUBLESHOOTING.md`

### SendGrid Troubleshooting Guide

#### Current Issue: "Maximum credits exceeded"

Your SendGrid account has reached its free tier limit (100 emails/day for free accounts).

#### Quick Fix: Use Log Driver (Temporary)

To test the email verification flow without SendGrid, switch to log driver:

##### Option 1: Update .env file

```env
MAIL_MAILER=log
```

Then clear config cache:
```bash
php artisan config:clear
```

**Note**: Emails will be written to `storage/logs/laravel.log` instead of being sent. You can view the email content there.

##### Option 2: Test Email Content

To see what the email would look like, check the log file:
```bash
tail -f storage/logs/laravel.log
```

Then trigger a verification email and look for the email content in the logs.

#### Fix SendGrid Account

##### Check Your SendGrid Account

1. **Login to SendGrid**: https://app.sendgrid.com/
2. **Check Usage**: 
   - Go to **Activity** → **Overview**
   - Check your daily/monthly email count
   - Free tier: 100 emails/day

##### Solutions

#### Option A: Wait for Reset
- Free tier resets daily at midnight UTC
- Wait until tomorrow to send more emails

#### Option B: Upgrade SendGrid Plan
- Go to **Settings** → **Billing**
- Upgrade to a paid plan for more credits
- Essential Plan: $19.95/month for 50,000 emails

#### Option C: Verify Domain (Increases Limits)
- Go to **Settings** → **Sender Authentication**
- Authenticate your domain
- This can increase your sending limits

#### Option D: Use Alternative Email Service

**Mailgun** (Free tier: 5,000 emails/month):
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-mailgun-username
MAIL_PASSWORD=your-mailgun-password
MAIL_ENCRYPTION=tls
```

**Postmark** (Free tier: 100 emails/month):
```env
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-postmark-token
```

**Amazon SES** (Pay as you go):
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
```

#### Verify Email Configuration

Check your current mail configuration:
```bash
php artisan tinker
>>> config('mail.default')
>>> config('mail.mailers.smtp.host')
>>> config('mail.from.address')
```

#### Test Email Sending

##### Test with Log Driver
```bash
### Set MAIL_MAILER=log in .env
php artisan config:clear

### Send test email
php artisan tinker
>>> Mail::raw('Test email', function($message) {
...     $message->to('your-email@example.com')->subject('Test');
... });

### Check logs
tail -f storage/logs/laravel.log
```

##### Test with SendGrid (after fixing account)
```bash
### Make sure MAIL_MAILER=smtp in .env
php artisan config:clear

### Test send
php artisan tinker
>>> Mail::raw('Test email', function($message) {
...     $message->to('your-email@example.com')->subject('Test');
... });
```

#### Current Status

- ✅ Email verification code is working
- ✅ Error handling is in place
- ⚠️ SendGrid account needs attention (credits exceeded)
- ✅ Can use log driver for testing

#### Next Steps

1. **Immediate**: Switch to `MAIL_MAILER=log` to test functionality
2. **Short-term**: Check SendGrid account and wait for reset or upgrade
3. **Long-term**: Consider domain authentication or alternative service

#### Monitoring

Check email sending status:
```bash
### View recent email attempts
tail -50 storage/logs/laravel.log | grep -i "mail\|email\|sendgrid"

### Check for errors
tail -50 storage/logs/laravel.log | grep -i "error\|exception\|failed"
```
---

## QUICK_FIX_SENDGRID.md
<a id="appendix-quickfixsendgridmd"></a>

**Source file**: `QUICK_FIX_SENDGRID.md`

### Quick Fix: SendGrid Credits Exceeded

#### ✅ What I Just Did

I've temporarily switched your email driver to `log` mode so you can test the email verification functionality.

**Current Status**:
- ✅ Email verification code is working
- ✅ Emails will be logged to `storage/logs/laravel.log`
- ✅ You can test the full flow without SendGrid

#### How to View Emails

When you trigger a verification email, it will be written to the log file. To view it:

```bash
### Watch the log file in real-time
tail -f storage/logs/laravel.log

### Or view the last 100 lines
tail -100 storage/logs/laravel.log
```

The email content (HTML) will be in the log file, and you can see the verification link.

#### Fix SendGrid (When Ready)

##### Option 1: Wait for Daily Reset
- SendGrid free tier resets daily at midnight UTC
- Check your account: https://app.sendgrid.com/
- Wait until tomorrow to resume sending

##### Option 2: Upgrade SendGrid
- Go to SendGrid → Settings → Billing
- Upgrade to Essential Plan ($19.95/month for 50,000 emails)

##### Option 3: Switch Back to SendGrid
Once your SendGrid account is fixed:

```bash
### Edit .env file
MAIL_MAILER=smtp

### Clear config cache
php artisan config:clear
```

#### Test Email Verification Now

1. Go to a customer card page
2. Click "Verify Email" button
3. Check `storage/logs/laravel.log` for the email content
4. Copy the verification link from the log
5. Paste it in your browser to test verification

The verification flow will work - you just need to get the link from the log file instead of your inbox.

#### Alternative: Use Different Email Service

If you want to use a different service, see `SENDGRID_TROUBLESHOOTING.md` for options like Mailgun, Postmark, or Amazon SES.
---

## PASSWORD_RESET_SETUP.md
<a id="appendix-passwordresetsetupmd"></a>

**Source file**: `PASSWORD_RESET_SETUP.md`

### Password Reset Email Setup with SendGrid

#### Overview

The password reset functionality uses Laravel's built-in password reset system, which sends emails via the configured mail driver. This guide ensures password reset emails work with SendGrid in production.

#### Current Implementation

The password reset flow uses:
- `PasswordResetLinkController` - Handles forgot password requests
- Laravel's `Password::sendResetLink()` - Sends reset link via email
- Default `ResetPassword` notification - Uses configured mail driver

#### Configuration Required

##### 1. Production `.env` File

Ensure these settings are configured in your production `.env`:

```env
### Mail Configuration (REQUIRED for password reset)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_actual_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

### Queue Configuration (optional, but recommended)
QUEUE_CONNECTION=database
```

**Important Notes:**
- `MAIL_USERNAME` must be exactly `apikey` (SendGrid requirement)
- `MAIL_PASSWORD` should be your full SendGrid API key (starts with `SG.`)
- `MAIL_FROM_ADDRESS` must be a verified sender in SendGrid
- `MAIL_MAILER` must be `smtp` (not `log`) for production

##### 2. Verify SendGrid Sender

1. Login to [SendGrid Dashboard](https://app.sendgrid.com/)
2. Go to **Settings** → **Sender Authentication**
3. Verify your sender email (or domain)
4. Use the verified email as `MAIL_FROM_ADDRESS`

##### 3. Queue Worker (If Using Queues)

If `QUEUE_CONNECTION=database`, ensure queue worker is running:

```bash
php artisan queue:work --tries=3
```

Or use Supervisor/systemd to keep it running.

#### Testing Password Reset

##### Local Testing

1. Set `MAIL_MAILER=log` in `.env` for local testing
2. Check `storage/logs/laravel.log` for password reset emails
3. Or use Mailtrap/MailHog for local SMTP testing

##### Production Testing

1. Ensure SendGrid credentials are set in `.env`
2. Clear config cache: `php artisan config:clear && php artisan config:cache`
3. Test password reset flow:
   - Go to `/forgot-password`
   - Enter a valid user email
   - Check email inbox for reset link
   - Check `storage/logs/laravel.log` for any errors

#### Troubleshooting

##### Password Reset Link Not Received

1. **Check Mail Configuration:**
   ```bash
   php artisan tinker
   >>> config('mail.default')
   >>> config('mail.mailers.smtp.host')
   >>> config('mail.from.address')
   ```

2. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   Look for "Password reset link sent" or "Password reset link failed to send"

3. **Verify SendGrid API Key:**
   - Ensure API key has "Mail Send" permissions
   - Check API key is not expired/revoked
   - Verify `MAIL_USERNAME=apikey` (exact match)

4. **Check Sender Verification:**
   - Sender email must be verified in SendGrid
   - Domain must be authenticated (if using domain)

5. **Test SMTP Connection:**
   ```bash
   php artisan tinker
   >>> Mail::raw('Test email', function($message) {
   ...     $message->to('your-email@example.com')
   ...            ->subject('Test');
   ... });
   ```

6. **Check Queue (if using queues):**
   ```bash
   php artisan queue:work --once
   ```
   Or check failed jobs:
   ```bash
   php artisan queue:failed
   ```

##### Common Issues

**Issue: "Email not sent" but no error**
- Check `MAIL_MAILER` is `smtp` (not `log`)
- Verify SendGrid credentials are correct
- Check queue worker is running (if using queues)

**Issue: "Invalid credentials"**
- Verify `MAIL_USERNAME=apikey` (exact match, lowercase)
- Check `MAIL_PASSWORD` is the full API key (starts with `SG.`)
- Ensure API key has correct permissions

**Issue: "Sender not verified"**
- Verify sender email in SendGrid dashboard
- Use verified email as `MAIL_FROM_ADDRESS`
- Wait for verification to complete (can take a few minutes)

#### Deployment Checklist

- [ ] SendGrid API key added to production `.env`
- [ ] `MAIL_MAILER=smtp` in production `.env`
- [ ] `MAIL_FROM_ADDRESS` is verified in SendGrid
- [ ] Config cache cleared and rebuilt: `php artisan config:clear && php artisan config:cache`
- [ ] Queue worker running (if using queues)
- [ ] Test password reset flow in production
- [ ] Check logs for any errors

#### Code Changes Made

1. **Added logging to `PasswordResetLinkController`:**
   - Logs successful password reset link sends
   - Logs failures with mail configuration details
   - Helps debug email delivery issues

2. **No changes needed to:**
   - User model (uses default `ResetPassword` notification)
   - Routes (already configured)
   - Views (already configured)

#### Additional Notes

- Password reset emails use Laravel's default `ResetPassword` notification
- Emails are sent synchronously (unless queue is configured)
- Reset links expire after 60 minutes (Laravel default)
- Reset tokens are one-time use only
---

## EMAIL_VERIFICATION_FIX.md
<a id="appendix-emailverificationfixmd"></a>

**Source file**: `EMAIL_VERIFICATION_FIX.md`

### Email Verification Fix

#### Problem
Verification emails were not being sent because:
1. Emails were queued but queue worker wasn't running
2. The Mailable class implemented `ShouldQueue` which forced queuing
3. No fallback mechanism if queue failed

#### Solution

##### Changes Made

1. **Removed `ShouldQueue` from Mailable** (`app/Mail/VerifyCustomerEmail.php`)
   - Removed `implements ShouldQueue`
   - Now the mailable can be sent synchronously or queued based on controller logic

2. **Updated Controller Logic** (`app/Http/Controllers/CustomerEmailVerificationController.php`)
   - Sends synchronously in `local` environment or when queue is `sync`
   - Queues in production environments
   - Added comprehensive error handling and logging

3. **Added Error Handling**
   - Catches exceptions during email sending
   - Logs errors for debugging
   - Returns user-friendly error messages

#### How It Works Now

##### Development (Local Environment)
- Emails are sent **synchronously** (immediately)
- No queue worker needed
- Errors are logged to `storage/logs/laravel.log`

##### Production
- Emails are **queued** for background processing
- Requires queue worker running: `php artisan queue:work`
- Failed jobs can be retried: `php artisan queue:retry all`

#### Testing

##### Test Email Sending

1. **Check Mail Configuration**:
   ```bash
   php artisan tinker
   >>> config('mail.default')
   >>> config('mail.mailers.smtp.host')
   >>> config('mail.from.address')
   ```

2. **Test Sending Email**:
   - Go to a customer card page: `/c/{public_token}`
   - Click "Verify Email" button
   - Check logs: `tail -f storage/logs/laravel.log`
   - Check your email inbox

3. **Check Queue Status** (if using queues):
   ```bash
   php artisan queue:failed
   php artisan queue:work
   ```

##### Verify SendGrid Configuration

Make sure your `.env` has:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_actual_sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"
```

#### Troubleshooting

##### Emails Still Not Sending?

1. **Check Logs**:
   ```bash
   tail -f storage/logs/laravel.log | grep -i "email\|mail\|verify"
   ```

2. **Test SendGrid Connection**:
   ```bash
   php artisan tinker
   >>> Mail::raw('Test email', function($message) {
   ...     $message->to('your-email@example.com')->subject('Test');
   ... });
   ```

3. **Check Environment**:
   - If `APP_ENV=local`, emails send synchronously
   - If `APP_ENV=production`, emails are queued (need queue worker)

4. **Verify SendGrid**:
   - Check SendGrid dashboard for activity
   - Verify sender email is authenticated
   - Check API key permissions

##### Queue Worker Not Running?

If you're in production and emails are queued:
```bash
### Start queue worker
php artisan queue:work

### Or use supervisor for production
### See: https://laravel.com/docs/queues#supervisor-configuration
```

#### Next Steps

1. **Test the fix**: Try sending a verification email from a customer card
2. **Check logs**: Verify emails are being sent/logged
3. **Monitor**: Check SendGrid dashboard for email delivery
4. **Production**: Set up queue worker if using production environment
---

## EMAIL_VERIFICATION_TEST_CHECKLIST.md
<a id="appendix-emailverificationtestchecklistmd"></a>

**Source file**: `EMAIL_VERIFICATION_TEST_CHECKLIST.md`

### Email Verification Test Checklist

This document provides step-by-step test procedures to verify email verification works correctly in both local and production environments.

#### Prerequisites

- Application deployed and accessible
- Database configured and migrated
- Queue worker running (for production)
- SendGrid account configured (for production)

#### Test 1: Local Test with Log Driver

**Purpose:** Verify email queuing works without external dependencies.

##### Steps:

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

##### Success Criteria:
- ✅ Customer creation never fails due to email issues
- ✅ Verification email is queued successfully
- ✅ Verification link works and redirects correctly
- ✅ Redemption only works after email verification
- ✅ Unverified customers cannot redeem

---

#### Test 2: Production Test with SendGrid (Normal Operation)

**Purpose:** Verify email sending works with SendGrid in production.

##### Steps:

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
   APP_URL=https://testing.kawhe.shop
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

##### Success Criteria:
- ✅ Test email command works
- ✅ Real verification emails are sent via SendGrid
- ✅ Verification links work correctly
- ✅ Emails arrive in inbox (not spam)

---

#### Test 3: SendGrid Down / Credits Exceeded

**Purpose:** Verify application continues working when SendGrid is unavailable.

##### Steps:

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

##### Success Criteria:
- ✅ Customer creation never fails
- ✅ Verification request succeeds (email queued)
- ✅ Failed jobs are logged and can be retried
- ✅ Card functionality (stamping) works normally
- ✅ Failed emails can be retried after fixing SendGrid

---

#### Test 4: Queue Worker Stopped

**Purpose:** Verify queued emails accumulate when worker is stopped.

##### Steps:

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

##### Success Criteria:
- ✅ Customer creation works when queue worker is stopped
- ✅ Emails accumulate in queue
- ✅ Queue worker processes all accumulated jobs when started
- ✅ All emails are sent after worker starts

---

#### Test 5: Verification Link Expiry

**Purpose:** Verify expired tokens are handled gracefully.

##### Steps:

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

##### Success Criteria:
- ✅ Expired tokens show friendly error (no 500)
- ✅ Users can request new verification email
- ✅ New tokens work correctly

---

#### Test 6: Multiple Rewards with Verification

**Purpose:** Verify email verification works with multiple rewards system.

##### Steps:

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

##### Success Criteria:
- ✅ Verified customers can redeem multiple rewards
- ✅ Unverified customers cannot redeem
- ✅ Verification status persists across multiple redemptions

---

#### Quick Verification Commands

```bash
### Check queue status
php artisan queue:monitor

### Check failed jobs
php artisan queue:failed

### Retry all failed jobs
php artisan queue:retry all

### Test email configuration
php artisan kawhe:mail-test your-email@example.com

### Check queue worker status (supervisor)
sudo supervisorctl status kawhe-queue-worker:*

### Check queue worker status (systemd)
sudo systemctl status kawhe-queue-worker

### View logs
tail -f storage/logs/laravel.log
tail -f storage/logs/queue-worker.log
```

---

#### Common Issues and Solutions

##### Issue: Emails not sending
**Solution:**
1. Check queue worker is running
2. Check SendGrid API key is correct
3. Check SendGrid account has credits
4. Check logs: `tail -f storage/logs/laravel.log`

##### Issue: Verification links not working
**Solution:**
1. Check `APP_URL` matches production domain
2. Check `APP_ENV=production` (forces HTTPS)
3. Verify link in email matches `APP_URL`

##### Issue: Queue worker not processing
**Solution:**
1. Check worker is running: `sudo supervisorctl status`
2. Check logs for errors
3. Restart worker: `sudo supervisorctl restart kawhe-queue-worker:*`

##### Issue: Failed jobs accumulating
**Solution:**
1. Check error in logs
2. Fix underlying issue (e.g., SendGrid API key)
3. Retry jobs: `php artisan queue:retry all`
---

## TEST_EMAIL_VERIFICATION_LOCAL.md
<a id="appendix-testemailverificationlocalmd"></a>

**Source file**: `TEST_EMAIL_VERIFICATION_LOCAL.md`

### Testing Email Verification & Reward Claiming Locally

#### Current Setup ✅

- **MAIL_MAILER**: `log` (emails written to logs, not sent)
- **APP_ENV**: `local` (emails send synchronously)
- **Production**: Will use SendGrid (configured in `.env` for production)

#### Testing Flow

##### Step 1: Start the App

Make sure these are running:
```bash
### Terminal 1
php artisan serve --port=8000

### Terminal 2 (optional for real-time updates)
php artisan reverb:start
```

##### Step 2: Create Test Data

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

##### Step 3: Create Customer Account

1. **Open join link** in a new tab/incognito window
2. **Click "New Customer"**
3. **Enter details**:
   - Name: Test Customer
   - Email: test@example.com (or your real email)
4. **Submit** → You'll be redirected to the loyalty card

##### Step 4: Test Email Verification

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

##### Step 5: Test Reward Claiming

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

#### Quick Test Script

Here's a quick way to test without waiting:

##### Option 1: Manually Verify Email (Skip Email Step)

```bash
php artisan tinker
```

Then run:
```php
$customer = \App\Models\Customer::where('email', 'test@example.com')->first();
$customer->update(['email_verified_at' => now()]);
```

Now you can test redemption immediately!

##### Option 2: Extract Verification Link from Logs

```bash
### Watch logs in real-time
tail -f storage/logs/laravel.log | grep -i "verify-email"

### Or search for the link
grep -o "http://localhost:8000/verify-email/[^ ]*" storage/logs/laravel.log | tail -1
```

#### Expected Behavior

##### ✅ Email Verification Flow
- [ ] Blue banner appears on card if email not verified
- [ ] "Verify Email" button works
- [ ] Email content appears in logs
- [ ] Verification link works when clicked
- [ ] Card updates to show email is verified
- [ ] Redeem QR appears (if reward available)

##### ✅ Reward Claiming Flow
- [ ] Stamps can be added via scanner
- [ ] Reward becomes available when target reached
- [ ] Redeem QR code appears (if email verified)
- [ ] Lock icon appears if email not verified
- [ ] Redemption works when scanning redeem QR
- [ ] Stamps are deducted after redemption
- [ ] New cycle starts

#### Troubleshooting

##### Email Not in Logs?
- Check: `storage/logs/laravel.log`
- Make sure `MAIL_MAILER=log` in `.env`
- Clear cache: `php artisan config:clear`

##### Verification Link Not Working?
- Check token hasn't expired (60 minutes)
- Make sure you copy the FULL URL including `?card=...`
- Check logs for errors

##### Can't Redeem Reward?
- Verify email is verified: Check `email_verified_at` in database
- Make sure reward is available: Check `reward_available_at` is set
- Make sure reward not already redeemed: Check `reward_redeemed_at` is null

##### Check Database Directly

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

#### Production Notes

When deploying to Digital Ocean:
- Set `MAIL_MAILER=smtp` in production `.env`
- Configure SendGrid credentials
- Run `php artisan queue:work` for email processing
- Everything else works the same way!
---

## PRODUCTION_DEPLOY_CHECKLIST.md
<a id="appendix-productiondeploychecklistmd"></a>

**Source file**: `PRODUCTION_DEPLOY_CHECKLIST.md`

### Production Deploy Checklist - Stripe Config Fix

#### ⚠️ Critical: Config Cache Issue

After deploying, `STRIPE_PRICE_ID` may show as "Not set" even if it's in your `.env` file. This happens because:

1. The new `price_id` key was added to `config/cashier.php`
2. If config is cached without this key, it won't be available
3. **Solution: Always clear and recache config after deployment**

#### Quick Deploy Commands

```bash
### 1. Pull code
git pull origin main

### 2. Install dependencies
composer install --no-dev --optimize-autoloader

### 3. Run migrations (if any)
php artisan migrate --force

### 4. ⚠️ CRITICAL: Clear and recache config
php artisan config:clear
php artisan config:cache

### 5. Cache routes and views
php artisan route:cache
php artisan view:cache

### 6. Restart services
sudo systemctl restart php-fpm
sudo supervisorctl restart kawhe-queue-worker:*
```

#### Verify Config is Working

After deployment, verify the config:

```bash
php artisan tinker
```

Then run:
```php
config('cashier.price_id')
```

**Expected:** Should return your `STRIPE_PRICE_ID` value (e.g., `"price_1AbC123..."`)

**If null:** Config cache is stale. Run:
```bash
php artisan config:clear && php artisan config:cache
```

#### Check Billing Page

1. Go to `https://yourdomain.com/billing`
2. Check the debug section at bottom
3. All three should show "✅ Set":
   - STRIPE_KEY: ✅ Set
   - STRIPE_SECRET: ✅ Set
   - STRIPE_PRICE_ID: ✅ Set

If `STRIPE_PRICE_ID` shows "❌ Not set":
- Verify it's in `.env` file
- Run `php artisan config:clear && php artisan config:cache`
- Refresh the page

#### Environment Variables Required

Make sure your production `.env` has:

```env
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_PRICE_ID=price_...  # ⚠️ Must be set
STRIPE_WEBHOOK_SECRET=whsec_...
APP_URL=https://yourdomain.com
```

#### Why This Happens

Laravel caches configuration files for performance. When you:
1. Add a new config key (`price_id` in `config/cashier.php`)
2. Deploy to production
3. Don't clear the old cached config

The cached config doesn't have the new key, so `config('cashier.price_id')` returns `null` even though it's in `.env`.

**Solution:** Always run `php artisan config:clear && php artisan config:cache` after deploying config changes.
---

## PRODUCTION_EMAIL_SETUP.md
<a id="appendix-productionemailsetupmd"></a>

**Source file**: `PRODUCTION_EMAIL_SETUP.md`

### Production Email Setup Guide

This guide covers setting up production-ready email verification using SendGrid with Laravel queues.

#### Prerequisites

- Laravel 11 application
- Database configured (for queue jobs table)
- SendGrid account with API key

#### Step 1: Environment Configuration

Add these to your `.env` file:

```env
### Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Kawhe Loyalty"

### Queue Configuration
QUEUE_CONNECTION=database

### App URL (must be correct for verification links)
APP_URL=https://testing.kawhe.shop
APP_ENV=production
```

**Important:** 
- `MAIL_USERNAME` must be exactly `apikey` for SendGrid
- `MAIL_PASSWORD` is your SendGrid API key (not your SendGrid password)
- `APP_URL` must match your production domain exactly

#### Step 2: Database Queue Setup

The queue uses the database driver. Ensure the jobs table exists:

```bash
php artisan migrate
```

This creates:
- `jobs` table (queued jobs)
- `job_batches` table (batch jobs)
- `failed_jobs` table (failed job tracking)

#### Step 3: Queue Worker Setup

##### Option A: Using Supervisor (Recommended for Production)

Create supervisor config file at `/etc/supervisor/conf.d/kawhe-queue-worker.conf`:

```ini
[program:kawhe-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start kawhe-queue-worker:*
```

##### Option B: Using systemd (Alternative)

Create systemd service file at `/etc/systemd/system/kawhe-queue-worker.service`:

```ini
[Unit]
Description=Kawhe Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/your/app/artisan queue:work database --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable kawhe-queue-worker
sudo systemctl start kawhe-queue-worker
sudo systemctl status kawhe-queue-worker
```

##### Option C: Manual (For Testing)

For testing or development, you can run the queue worker manually:

```bash
php artisan queue:work database --sleep=3 --tries=3
```

**Note:** This runs in the foreground. Use Ctrl+C to stop.

#### Step 4: Testing Email Configuration

Test your email setup:

```bash
php artisan kawhe:mail-test your-email@example.com
```

This will:
1. Queue a test verification email
2. Show queue status
3. Provide troubleshooting tips if it fails

Then process the queue:

```bash
php artisan queue:work
```

Check the email was sent (or check logs if using `log` driver).

#### Step 5: Monitoring

##### Check Queue Status

```bash
### View pending jobs
php artisan queue:monitor

### View failed jobs
php artisan queue:failed

### Retry failed jobs
php artisan queue:retry all
```

##### Check Logs

```bash
### View application logs
tail -f storage/logs/laravel.log

### View queue worker logs (if using supervisor)
tail -f storage/logs/queue-worker.log
```

#### Troubleshooting

##### Emails Not Sending

1. **Check SendGrid Account:**
   - Verify API key is correct
   - Check SendGrid account has credits
   - Verify sender email is verified in SendGrid

2. **Check Queue Worker:**
   ```bash
   # Check if worker is running
   sudo supervisorctl status kawhe-queue-worker:*
   # OR
   sudo systemctl status kawhe-queue-worker
   
   # Check for failed jobs
   php artisan queue:failed
   ```

3. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i mail
   ```

##### SendGrid Errors

If you see "Maximum credits exceeded" or "Authentication failed":

1. Check SendGrid dashboard for account status
2. Verify API key is correct in `.env`
3. Emails will be retried automatically (3 attempts with backoff)
4. Failed jobs can be retried: `php artisan queue:retry all`

##### Verification Links Not Working

1. **Check APP_URL:**
   ```bash
   php artisan tinker
   >>> config('app.url')
   ```
   Should match your production domain exactly.

2. **Check HTTPS:**
   - Ensure `APP_ENV=production` in `.env`
   - App automatically forces HTTPS in production

3. **Test verification link:**
   - Request verification email
   - Check email for link
   - Click link and verify it redirects correctly

#### Fallback to Log Driver

If SendGrid is down, you can temporarily switch to log driver for testing:

```env
MAIL_MAILER=log
```

Emails will be written to `storage/logs/laravel.log` instead of being sent.

**Important:** The application will continue to work even if email sending fails. Customer creation and card usage are not blocked by email failures.

#### After Deployment Checklist

After deploying from git, run these commands:

```bash
### 1. Install dependencies (if needed)
composer install --no-dev --optimize-autoloader

### 2. Run migrations (creates jobs table if needed)
php artisan migrate --force

### 3. Clear and cache config
php artisan config:clear
php artisan config:cache

### 4. Restart queue worker
sudo supervisorctl restart kawhe-queue-worker:*
### OR
sudo systemctl restart kawhe-queue-worker

### 5. Test email
php artisan kawhe:mail-test your-email@example.com

### 6. Process queue to send test email
php artisan queue:work --once
```

#### Security Notes

- Never commit `.env` file to git
- Store SendGrid API key securely
- Use environment variables for all sensitive data
- Rotate API keys periodically
---

## PRODUCTION_SERVICE_RESTART.md
<a id="appendix-productionservicerestartmd"></a>

**Source file**: `PRODUCTION_SERVICE_RESTART.md`

### Production Service Restart Guide

#### Finding the Correct PHP-FPM Service Name

The service name varies by PHP version and system. Try these commands to find yours:

##### 1. Check Available PHP-FPM Services

```bash
### List all PHP-FPM services
systemctl list-units | grep php
### OR
systemctl list-units | grep fpm

### Check what's running
systemctl status php*-fpm*
```

##### 2. Common Service Names

Try these (replace X.X with your PHP version):

```bash
### PHP 8.2
sudo systemctl restart php8.2-fpm

### PHP 8.3
sudo systemctl restart php8.3-fpm

### PHP 8.1
sudo systemctl restart php8.1-fpm

### Generic (some systems)
sudo systemctl restart php-fpm
sudo service php-fpm restart
```

##### 3. Find Your PHP Version

```bash
php -v
```

This will show your PHP version (e.g., PHP 8.2.15), then use that version in the service name.

##### 4. Alternative: Restart Web Server Instead

If you can't find php-fpm, restarting your web server (Nginx/Apache) will also reload PHP:

**For Nginx:**
```bash
sudo systemctl restart nginx
### OR
sudo service nginx restart
```

**For Apache:**
```bash
sudo systemctl restart apache2
### OR (on some systems)
sudo systemctl restart httpd
### OR
sudo service apache2 restart
```

##### 5. Check if PHP-FPM is Even Needed

If you're using mod_php (Apache) or PHP-CGI, you don't need to restart php-fpm. Just restart the web server.

##### 6. For Laravel Specifically

After config changes, you might not need to restart anything if you're using:
- **PHP-FPM with opcache disabled** - Config changes take effect immediately
- **PHP-FPM with opcache enabled** - You need to restart php-fpm OR clear opcache

**Clear opcache instead:**
```bash
### Create a route to clear opcache (temporary)
### Or restart php-fpm if you find the service name
```

#### Complete Deployment Without PHP-FPM Restart

If you can't restart php-fpm, you can still deploy successfully:

```bash
### 1. Pull code
git pull origin main

### 2. Install dependencies
composer install --no-dev --optimize-autoloader

### 3. Run migrations
php artisan migrate --force

### 4. Clear and cache config (THIS IS THE CRITICAL STEP)
php artisan config:clear
php artisan config:cache

### 5. Cache routes and views
php artisan route:cache
php artisan view:cache

### 6. Restart web server (this will reload PHP)
sudo systemctl restart nginx
### OR
sudo systemctl restart apache2

### 7. Restart queue workers (if using)
sudo supervisorctl restart kawhe-queue-worker:*
### OR
sudo systemctl restart kawhe-queue-worker
```

#### Verify It Worked

After deployment, check:

```bash
### Verify config is cached
php artisan tinker
>>> config('cashier.price_id')
### Should return your STRIPE_PRICE_ID

### Check application is running
curl https://yourdomain.com/up
```

#### Troubleshooting

##### If config changes don't take effect:

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

#### Quick Reference

**Most important commands for this deployment:**
```bash
php artisan config:clear
php artisan config:cache
sudo systemctl restart nginx  # or apache2
```

The web server restart is usually sufficient to reload PHP and pick up config changes.
