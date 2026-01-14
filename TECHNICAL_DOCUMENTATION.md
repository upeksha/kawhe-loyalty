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
**Routes**: `/merchant/stores/*`

**Features**:
- Create stores with custom names, addresses, and reward configurations
- Set reward target (e.g., "10 stamps = Free Coffee")
- Upload store logo
- Set brand color (for card UI)
- Set background color (for card page background)
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
- Store branding (logo, colors)
- Customer name and masked card ID
- Progress visualization (circular stamp dots)
- Reward status (available, redeemed, locked)
- Recent activity/transaction history
- Email verification status
- Real-time updates via WebSocket

**Card States**:
- **Collecting**: `reward_balance = 0`, stamps < target, reward not available
- **Reward Available**: `reward_balance > 0`, reward(s) available to redeem
- **Multiple Rewards**: `reward_balance > 1`, customer can redeem multiple rewards
- **Reward Redeemed**: Reward was redeemed, but `reward_balance` may still be > 0 if multiple rewards existed

### 5. QR Code Scanner
**Route**: `/merchant/scanner`

**Functionality**:
- Merchant selects store from dropdown
- Scans customer's QR code (stamp or redeem)
- System validates token and processes transaction
- Real-time feedback with customer name and stamp count
- Prevents double-scanning with cooldown periods

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
