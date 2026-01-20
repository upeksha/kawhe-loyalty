# Kawhe Loyalty - Complete Technical Documentation

**Version:** 2.0  
**Last Updated:** January 2026

This document provides a complete, comprehensive explanation of the Kawhe Loyalty application from A to Z. It covers architecture, implementation, features, logic, and how everything works together.

---

## Table of Contents

1. [Overview](#1-overview)
2. [System Architecture](#2-system-architecture)
3. [Database Schema & Models](#3-database-schema--models)
4. [Core Features & User Flows](#4-core-features--user-flows)
5. [Implementation Details](#5-implementation-details)
6. [Services & Business Logic](#6-services--business-logic)
7. [API Endpoints & Routes](#7-api-endpoints--routes)
8. [Real-time Features](#8-real-time-features)
9. [Security & Data Integrity](#9-security--data-integrity)
10. [Billing & Subscription System](#10-billing--subscription-system)
11. [Wallet Integrations](#11-wallet-integrations)
12. [Deployment & Production](#12-deployment--production)
13. [Troubleshooting Guide](#13-troubleshooting-guide)

---

## 1. Overview

### What is Kawhe Loyalty?

Kawhe Loyalty is a **Progressive Web App (PWA)** loyalty card system built with Laravel 11. It enables merchants to create digital loyalty programs where customers earn stamps and redeem rewards. The system features real-time updates, email verification, store branding, subscription billing, and native wallet integrations (Apple Wallet & Google Wallet).

### Key Capabilities

- **Multi-Store Management**: Merchants can create and manage multiple stores
- **Digital Stamping**: Scan QR codes to add stamps to customer cards
- **Multiple Rewards**: Customers can accumulate multiple rewards (e.g., 18 stamps on 9-target card = 2 rewards)
- **Flexible Redemption**: Merchants can redeem 1, 2, 3... or all available rewards per scan
- **Real-time Updates**: Live synchronization via Laravel Reverb (WebSockets)
- **Transaction Ledger**: Immutable audit trail of all point transactions
- **Email Verification**: Required for reward redemption
- **Store Branding**: Custom logos, colors, and backgrounds per store
- **Subscription Billing**: Stripe integration via Laravel Cashier
- **Wallet Integration**: Apple Wallet and Google Wallet pass generation with auto-updates

### Tech Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Tailwind CSS, Alpine.js, Vite
- **Real-time**: Laravel Reverb (WebSocket server)
- **Database**: SQLite (dev) / PostgreSQL/MySQL (production)
- **Queue System**: Database queues for async jobs
- **Email**: SendGrid SMTP
- **Billing**: Laravel Cashier (Stripe)
- **Apple Wallet**: byte5/laravel-passgenerator
- **Google Wallet**: google/apiclient
- **QR Codes**: SimpleSoftwareIO/QrCode

---

## 2. System Architecture

### High-Level Architecture

```
┌─────────────────┐
│   Web Browser   │
│   (Customer)    │
└────────┬────────┘
         │
         │ HTTP/WebSocket
         │
┌────────▼─────────────────────────────────────┐
│         Laravel Application                  │
│  ┌──────────────────────────────────────┐   │
│  │  Controllers (HTTP Requests)        │   │
│  └──────────┬──────────────────────────┘   │
│             │                                │
│  ┌──────────▼──────────────────────────┐   │
│  │  Services (Business Logic)          │   │
│  └──────────┬──────────────────────────┘   │
│             │                                │
│  ┌──────────▼──────────────────────────┐   │
│  │  Models (Database)                  │   │
│  └──────────────────────────────────────┘   │
└────────┬─────────────────────────────────────┘
         │
         │
┌────────▼────────┐    ┌──────────────┐    ┌─────────────┐
│   Database   │    │  Queue Worker  │    │   Reverb    │
│  (SQLite/     │    │  (Jobs)       │    │ (WebSocket) │
│   MySQL)      │    │               │    │             │
└────────────────┘    └──────────────┘    └─────────────┘
         │
         │
┌────────▼─────────────────────────────────────┐
│  External Services                            │
│  - Stripe (Billing)                           │
│  - SendGrid (Email)                          │
│  - Apple APNs (Push Notifications)           │
│  - Google Wallet API                         │
└───────────────────────────────────────────────┘
```

### Request Flow

1. **HTTP Request** → Route → Middleware → Controller
2. **Controller** → Service (business logic)
3. **Service** → Model (database operations)
4. **Service** → Dispatch Events/Jobs
5. **Event** → Broadcast via Reverb (real-time)
6. **Job** → Queue Worker (async processing)

### Directory Structure

```
app/
├── Console/Commands/          # Artisan commands
├── Events/                    # Event classes
├── Http/
│   ├── Controllers/          # HTTP controllers
│   │   ├── Auth/            # Authentication
│   │   └── Wallet/           # Wallet controllers
│   ├── Middleware/           # Custom middleware
│   └── Requests/             # Form requests
├── Jobs/                      # Queue jobs
├── Models/                    # Eloquent models
└── Services/                  # Business logic services
    ├── Billing/              # Billing logic
    ├── Loyalty/              # Stamping logic
    └── Wallet/                # Wallet integrations
        ├── Apple/            # Apple Wallet services
        └── Google/           # Google Wallet services
```

---

## 3. Database Schema & Models

### Core Tables

#### `users`
Merchants who own stores.

**Columns:**
- `id` (primary key)
- `name`, `email`, `password`
- `stripe_id` (Stripe customer ID)
- `is_super_admin` (boolean)
- `email_verified_at`, `remember_token`
- Timestamps

**Relationships:**
- `hasMany(Store)` - A user can own multiple stores

#### `stores`
Physical or virtual locations where loyalty programs run.

**Columns:**
- `id` (primary key)
- `user_id` (foreign key to users)
- `name`, `slug` (URL-friendly identifier)
- `address`
- `reward_target` (stamps needed for reward, default: 10)
- `reward_title` (e.g., "Free coffee")
- `join_token` (32-char secret for join links)
- `brand_color` (hex color for branding)
- `logo_path` (path to uploaded logo)
- `background_color` (hex color for card page)
- Timestamps

**Relationships:**
- `belongsTo(User)` - Each store belongs to one merchant
- `hasMany(LoyaltyAccount)` - A store has many loyalty accounts

#### `customers`
Customer information (can have multiple loyalty accounts across stores).

**Columns:**
- `id` (primary key)
- `name`, `email`, `phone`
- `email_verified_at`
- `email_verification_token_hash`
- `email_verification_expires_at`
- `email_verification_sent_at`
- Timestamps

**Relationships:**
- `hasMany(LoyaltyAccount)` - A customer can have multiple loyalty accounts

#### `loyalty_accounts`
The core entity - links a customer to a store with stamp/reward tracking.

**Columns:**
- `id` (primary key)
- `store_id` (foreign key)
- `customer_id` (foreign key)
- `stamp_count` (current stamps, resets when reward earned)
- `reward_balance` (number of available rewards)
- `public_token` (40-char token for public card access)
- `wallet_auth_token` (40-char token for Apple Wallet auth)
- `redeem_token` (40-char token for redemption, rotates after use)
- `last_stamped_at` (timestamp)
- `reward_available_at` (timestamp when first reward earned)
- `reward_redeemed_at` (timestamp of last redemption)
- `verified_at` (email verification timestamp)
- `version` (optimistic locking counter)
- Timestamps

**Relationships:**
- `belongsTo(Store)`
- `belongsTo(Customer)`
- `hasMany(StampEvent)`
- `hasMany(PointsTransaction)`

**Key Logic:**
- `stamp_count` resets to 0 when it reaches `reward_target`
- `reward_balance` increments when stamps reach target
- Multiple rewards possible: 18 stamps on 9-target card = 2 rewards

#### `stamp_events`
Audit log of all stamping operations.

**Columns:**
- `id` (primary key)
- `loyalty_account_id` (foreign key)
- `store_id`, `user_id`
- `type` ('stamp' or 'redeem')
- `count` (number of stamps added/redeemed)
- `idempotency_key` (unique, prevents duplicates)
- `user_agent`, `ip_address`
- Timestamps

**Unique Constraint:** `idempotency_key` ensures no duplicate operations

#### `points_transactions`
Immutable ledger of all point/stamp transactions.

**Columns:**
- `id` (primary key)
- `loyalty_account_id`, `store_id`, `user_id`
- `type` ('earn' or 'redeem')
- `points` (points/stamps amount)
- `idempotency_key` (unique)
- `metadata` (JSON with before/after states)
- `user_agent`, `ip_address`
- Timestamps

#### `apple_wallet_registrations`
Device registrations for Apple Wallet auto-updates.

**Columns:**
- `id` (primary key)
- `device_library_identifier` (unique device ID)
- `push_token` (APNs push token)
- `pass_type_identifier` (e.g., "pass.com.kawhe.loyalty")
- `serial_number` (e.g., "kawhe-1-2")
- `loyalty_account_id` (foreign key)
- `active` (boolean)
- `last_registered_at`
- Timestamps

**Unique Constraint:** `(device_library_identifier, pass_type_identifier, serial_number)`

#### `subscriptions` (Laravel Cashier)
Stripe subscription records.

**Columns:**
- `id` (primary key)
- `user_id` (foreign key)
- `type` ('default')
- `stripe_id`, `stripe_status`
- `stripe_price`, `stripe_plan`
- `quantity`
- `trial_ends_at`, `ends_at`
- Timestamps

### Model Relationships Diagram

```
User
  └─ hasMany → Store
                  └─ hasMany → LoyaltyAccount
                                    ├─ belongsTo → Customer
                                    ├─ hasMany → StampEvent
                                    └─ hasMany → PointsTransaction

LoyaltyAccount
  └─ hasMany → AppleWalletRegistration
```

---

## 4. Core Features & User Flows

### 4.1 Merchant Onboarding Flow

**Route:** `/merchant/onboarding/store`

1. User registers → `RegisteredUserController`
2. If no stores exist → Redirect to onboarding
3. User creates first store → `OnboardingController::storeStore()`
4. Store created with:
   - Auto-generated `slug` (name + random 6 chars)
   - Auto-generated `join_token` (32 chars)
   - Default `reward_target` (10)
5. Redirect to merchant dashboard

**Implementation:**
- `OnboardingController` validates store data
- `Store` model boot() hook generates `slug` and `join_token`
- Middleware `EnsureMerchantHasStore` checks if user has stores

### 4.2 Customer Enrollment Flow

**Route:** `/join/{slug}?t={join_token}`

1. Customer receives join link (e.g., `/join/coffee-shop-XYz123?t=abc...`)
2. Customer chooses "New Customer" or "Existing Customer"
3. **New Customer:**
   - Enters name and email/phone
   - System creates `Customer` record
   - System creates `LoyaltyAccount` linking customer to store
   - Auto-generates `public_token` (40 chars)
   - Redirects to card page: `/c/{public_token}`
4. **Existing Customer:**
   - Enters email
   - System looks up existing `Customer`
   - If found, creates new `LoyaltyAccount` for this store
   - If not found, shows error

**Implementation:**
- `JoinController::store()` handles enrollment
- `LoyaltyAccount` boot() hook generates `public_token` and `wallet_auth_token`
- Customers can have multiple loyalty accounts (one per store)

### 4.3 Stamping Flow

**Route:** `POST /stamp`

1. Merchant opens scanner: `/merchant/scanner`
2. Scanner uses `html5-qrcode` library
3. Scanner reads QR code: `LA:{public_token}`
4. Frontend sends POST to `/stamp` with:
   - `token`: `LA:{public_token}`
   - `store_id`: Selected store
   - `count`: Number of stamps (default: 1)
   - `idempotency_key`: UUID (prevents duplicates)
5. Backend:
   - `ScannerController::store()` extracts token
   - Validates staff has access to store
   - Calls `StampLoyaltyService::stamp()`
6. **StampLoyaltyService Logic:**
   - Checks idempotency (prevents duplicate stamps)
   - Locks account row (`lockForUpdate()`)
   - Increments `stamp_count`
   - Calculates rewards:
     ```php
     while ($stamp_count >= $reward_target) {
         $stamp_count -= $reward_target;
         $reward_balance++;
     }
     ```
   - Updates `reward_available_at` and `redeem_token` if reward earned
   - Creates `StampEvent` audit log
   - Creates `PointsTransaction` ledger entry
   - Dispatches `UpdateWalletPassJob` (after commit)
   - Dispatches `StampUpdated` event (real-time)
7. Response: JSON with new `stamp_count` and `reward_balance`
8. Frontend updates UI
9. Real-time: WebSocket broadcasts to card page

**Cooldown Logic:**
- Prevents rapid duplicate scans
- Default: 30 seconds between stamps
- Can be overridden with `override_cooldown=true`

### 4.4 Redemption Flow

**Route:** `POST /redeem`

1. Customer earns reward (`reward_balance > 0`)
2. QR code changes to: `LR:{redeem_token}`
3. Merchant scans redeem QR
4. Frontend calls `POST /redeem/info` to get `reward_balance`
5. Merchant selects quantity (1, 2, 3... or "All")
6. Frontend sends `POST /redeem` with:
   - `token`: `LR:{redeem_token}`
   - `quantity`: Number of rewards to redeem
7. Backend:
   - `ScannerController::redeem()` validates
   - Checks `reward_balance >= quantity`
   - Decrements `reward_balance` by quantity
   - Sets `reward_redeemed_at`
   - **Rotates `redeem_token`** (prevents reuse)
   - Creates `StampEvent` (type: 'redeem')
   - Creates `PointsTransaction` (type: 'redeem')
   - Dispatches `UpdateWalletPassJob` (after commit)
   - Dispatches `StampUpdated` event
8. QR code updates to `LA:{public_token}` if `reward_balance = 0`
9. QR code stays `LR:{new_redeem_token}` if `reward_balance > 0`

**Token Rotation:**
- After redemption, `redeem_token` is regenerated
- Old QR codes become invalid
- Prevents reward reuse

### 4.5 Loyalty Card Display

**Route:** `/c/{public_token}`

**Features:**
- Wallet-style pass design
- Always-visible QR code (stamp or redeem based on `reward_balance`)
- Store branding (logo, colors, background)
- Progress visualization (circular stamp dots)
- Reward status display
- Recent activity/transaction history
- Real-time updates via WebSocket
- Auto-refresh after redemption

**QR Code Logic:**
```php
if ($account->reward_balance > 0 && $account->redeem_token) {
    $qrMessage = "LR:" . $account->redeem_token; // Redeem QR
} else {
    $qrMessage = "LA:" . $account->public_token; // Stamp QR
}
```

**Real-time Updates:**
- Listens to `loyalty-card.{public_token}` channel
- Receives `StampUpdated` events
- Updates UI without page refresh

### 4.6 Email Verification Flow

**Purpose:** Required for reward redemption

1. Customer clicks "Verify Email" on card page
2. System sends verification email via SendGrid
3. Email contains signed link: `/verify-email/{token}`
4. Customer clicks link
5. System verifies token and sets `verified_at`
6. Customer can now redeem rewards

**Implementation:**
- `CustomerEmailVerificationController` handles verification
- Tokens are hashed and expire after 24 hours
- Rate limited: 3 emails per 10 minutes

---

## 5. Implementation Details

### 5.1 Stamping Service (`StampLoyaltyService`)

**Location:** `app/Services/Loyalty/StampLoyaltyService.php`

**Purpose:** Centralized, safe stamping logic with full audit trail.

**Key Methods:**

#### `stamp()`
Main stamping method.

**Parameters:**
- `LoyaltyAccount $account` - Account to stamp
- `User $staff` - Merchant performing stamp
- `int $count` - Number of stamps (default: 1)
- `?string $idempotencyKey` - Optional (auto-generated if null)
- `?string $userAgent` - For audit
- `?string $ipAddress` - For audit

**Returns:** `StampResultDTO` with:
- `stampCount` - New stamp count
- `rewardBalance` - New reward balance
- `rewardTarget` - Target for rewards
- `lastStampedAt` - Timestamp
- `rewardEarned` - Boolean
- `isDuplicate` - Boolean

**Process:**
1. Generate idempotency key if not provided
2. Validate staff has access to store
3. Check idempotency (return existing result if duplicate)
4. Start database transaction
5. Lock account row (`lockForUpdate()`)
6. Increment `stamp_count` and `version`
7. Calculate rewards (handle overshoot):
   ```php
   while ($stamp_count >= $reward_target) {
       $stamp_count -= $reward_target;
       $reward_balance++;
   }
   ```
8. Update `reward_available_at` and `redeem_token` if needed
9. Save account
10. Create `StampEvent` audit log
11. Create `PointsTransaction` ledger entry
12. Dispatch `UpdateWalletPassJob` (after commit)
13. Dispatch `StampUpdated` event
14. Return result DTO

**Concurrency Safety:**
- `lockForUpdate()` prevents race conditions
- `version` column for optimistic locking
- Idempotency key prevents duplicate operations
- Transaction ensures atomicity

### 5.2 Usage Service (`UsageService`)

**Location:** `app/Services/Billing/UsageService.php`

**Purpose:** Calculate loyalty card usage and enforce subscription limits.

**Key Methods:**

#### `freeLimit()`
Returns free plan limit (50 cards).

#### `cardsCountForUser(User $user, bool $includeGrandfathered = true)`
Counts total loyalty cards for a merchant.

**Grandfathering Logic:**
- If subscription cancelled (`ends_at` set), cards created before cancellation are "grandfathered"
- `includeGrandfathered = false` excludes grandfathered cards
- Used to enforce limits: only non-grandfathered cards count toward limit

#### `canCreateCard(User $user)`
Checks if merchant can create new card.

**Logic:**
- Subscribed users: Always `true` (unlimited)
- Non-subscribed: `nonGrandfatheredCount < freeLimit()`

#### `getUsageStats(User $user)`
Returns usage statistics:
- `cards_count` - Total cards (including grandfathered)
- `non_grandfathered_count` - Cards counting toward limit
- `grandfathered_count` - Grandfathered cards
- `limit` - Free plan limit (50)
- `is_subscribed` - Subscription status
- `can_create_card` - Whether new cards can be created
- `usage_percentage` - Usage percentage

### 5.3 Wallet Sync Service (`WalletSyncService`)

**Location:** `app/Services/Wallet/WalletSyncService.php`

**Purpose:** Synchronize wallet passes when loyalty accounts change.

**Key Methods:**

#### `syncLoyaltyAccount(int $loyaltyAccountId)`
Synchronizes both Apple and Google Wallet passes.

**Process:**
1. Load `LoyaltyAccount` with relationships
2. Generate Apple Wallet pass (`.pkpass` file)
3. Generate Google Wallet pass (update via API)
4. Send Apple Wallet push notifications (if enabled)
5. Log results

**Called By:**
- `UpdateWalletPassJob` (queue job)

### 5.4 Apple Push Service (`ApplePushService`)

**Location:** `app/Services/Wallet/Apple/ApplePushService.php`

**Purpose:** Send APNs push notifications to Apple Wallet devices.

**Key Methods:**

#### `sendPassUpdatePushes(string $passTypeIdentifier, string $serialNumber)`
Sends push to all registered devices for a pass.

**Process:**
1. Check if push enabled (`WALLET_APPLE_PUSH_ENABLED`)
2. Find all active registrations for serial number
3. For each registration:
   - Generate/use cached JWT (valid 50 minutes)
   - Send HTTP/2 request to APNs
   - Payload: `{"aps":{}}` (empty, triggers pass update)
   - Headers:
     - `apns-topic: pass.com.kawhe.loyalty`
     - `apns-push-type: background`
     - `apns-priority: 5`
   - Log response (success or error)
4. Log batch results

**JWT Generation:**
- ES256 algorithm
- Key ID, Team ID from config
- Private key from `.p8` file
- Cached for 50 minutes (regenerated if older)

**APNs Endpoints:**
- Production: `https://api.push.apple.com`
- Sandbox: `https://api.sandbox.push.apple.com`
- Selected via `APPLE_APNS_USE_SANDBOX` config

---

## 6. Services & Business Logic

### Service Layer Architecture

Services encapsulate business logic, keeping controllers thin.

**Service Pattern:**
```php
Controller → Service → Model → Database
         ↓
      Events/Jobs
```

### Key Services

1. **StampLoyaltyService** - Stamping logic
2. **UsageService** - Billing/usage calculations
3. **WalletSyncService** - Wallet pass synchronization
4. **ApplePushService** - APNs push notifications
5. **ApplePassService** - Apple Wallet pass generation
6. **GoogleWalletPassService** - Google Wallet pass generation

### Event-Driven Architecture

**Events:**
- `StampUpdated` - Dispatched after stamp/redeem
  - Broadcasts to `loyalty-card.{public_token}` channel
  - Real-time UI updates

**Jobs:**
- `UpdateWalletPassJob` - Regenerates wallet passes
  - Dispatched after transaction commit
  - Runs async via queue worker

---

## 7. API Endpoints & Routes

### Public Routes

```
GET  /                          - Welcome page
GET  /start                     - Merchant onboarding start
GET  /join/{slug}               - Customer join page
GET  /join/{slug}/new           - New customer form
POST /join/{slug}/new           - Create new customer account
GET  /join/{slug}/existing       - Existing customer lookup
POST /join/{slug}/existing      - Lookup existing customer
GET  /c/{public_token}          - Loyalty card display
GET  /api/card/{public_token}   - Card data (JSON)
GET  /api/card/{public_token}/transactions - Transaction history
```

### Authenticated Routes

```
GET  /merchant/dashboard        - Merchant dashboard
GET  /merchant/stores           - List stores
POST /merchant/stores           - Create store
GET  /merchant/stores/{id}/edit - Edit store
PUT  /merchant/stores/{id}      - Update store
GET  /merchant/scanner          - QR scanner
POST /stamp                     - Add stamps
POST /redeem                    - Redeem rewards
POST /redeem/info               - Get redeem info
```

### Billing Routes

```
GET  /billing                   - Billing overview
GET  /billing/checkout          - Stripe Checkout
GET  /billing/portal            - Stripe Billing Portal
GET  /billing/success           - Checkout success
GET  /billing/cancel            - Checkout cancel
POST /billing/sync              - Manual subscription sync
```

### Wallet Routes

```
GET  /wallet/apple/{token}/download - Download Apple pass (signed)
GET  /wallet/google/{token}/save     - Save Google pass (signed)
```

### Apple Wallet Web Service Routes

```
POST /wallet/v1/devices/{deviceId}/registrations/{passType}/{serial}
     - Register device for updates

DELETE /wallet/v1/devices/{deviceId}/registrations/{passType}/{serial}
     - Unregister device

GET  /wallet/v1/passes/{passType}/{serial}
     - Get updated pass file

GET  /wallet/v1/devices/{deviceId}/registrations/{passType}?passesUpdatedSince=...
     - Get list of updated serials

POST /wallet/v1/log
     - Log endpoint (Apple diagnostics)
```

### Webhook Routes

```
POST /stripe/webhook            - Stripe webhook (CSRF excluded)
```

---

## 8. Real-time Features

### Laravel Reverb (WebSockets)

**Purpose:** Real-time updates to customer card pages when stamps/redeems occur.

**Setup:**
- Reverb server runs on port 8080
- Frontend connects via WebSocket
- Channels: `loyalty-card.{public_token}`

**Flow:**
1. Merchant stamps card
2. `StampUpdated` event dispatched
3. Event broadcasts to channel
4. Customer's browser receives update
5. UI updates without page refresh

**Frontend Implementation:**
```javascript
Echo.channel(`loyalty-card.${publicToken}`)
    .listen('StampUpdated', (e) => {
        // Update UI with new data
        updateCardUI(e.account);
    });
```

**Event Class:**
```php
class StampUpdated implements ShouldBroadcast
{
    public function __construct(public LoyaltyAccount $account) {}
    
    public function broadcastOn(): Channel
    {
        return new PrivateChannel("loyalty-card.{$this->account->public_token}");
    }
}
```

---

## 9. Security & Data Integrity

### Authentication & Authorization

**Authentication:**
- Laravel's built-in authentication
- Email/password login
- Session-based

**Authorization:**
- Middleware: `EnsureMerchantHasStore` - Ensures merchant has at least one store
- Middleware: `SuperAdmin` - Restricts admin routes
- Service-level: `StampLoyaltyService::validateStaffAccess()` - Ensures staff owns store

### Data Integrity Mechanisms

#### 1. Idempotency
- Every stamp/redeem operation requires `idempotency_key`
- Unique constraint on `stamp_events.idempotency_key`
- Prevents duplicate operations
- Retry-safe: Same key returns same result

#### 2. Optimistic Locking
- `version` column on `loyalty_accounts`
- Incremented on each update
- Prevents lost updates in concurrent scenarios

#### 3. Pessimistic Locking
- `lockForUpdate()` in `StampLoyaltyService`
- Locks row during transaction
- Prevents race conditions

#### 4. Transaction Safety
- All stamping operations wrapped in `DB::transaction()`
- Atomic: All or nothing
- Jobs dispatched after commit (`DB::afterCommit()`)

#### 5. Audit Trail
- `stamp_events` - All stamp/redeem operations
- `points_transactions` - Immutable ledger
- Includes: user, IP, user agent, timestamps

### Rate Limiting

**Endpoints:**
- `/stamp` - `rate.limit.stamps` middleware
- `/redeem` - `rate.limit.stamps` middleware
- `/join/{slug}/existing` - 10 requests per minute

### Signed URLs

**Wallet Downloads:**
- Apple Wallet: `/wallet/apple/{token}/download` (signed)
- Google Wallet: `/wallet/google/{token}/save` (signed)
- Prevents unauthorized access

### Token Security

**Tokens:**
- `public_token` - 40 random chars (card access)
- `wallet_auth_token` - 40 random chars (Apple Wallet auth)
- `redeem_token` - 40 random chars (rotates after redemption)
- `join_token` - 32 random chars (store join links)

**Token Rotation:**
- `redeem_token` regenerated after each redemption
- Prevents reward reuse

---

## 10. Billing & Subscription System

### Stripe Integration (Laravel Cashier)

**Purpose:** Subscription billing for merchants.

**Free Plan:**
- 50 loyalty cards limit
- Grandfathered cards don't count toward limit

**Pro Plan:**
- Unlimited loyalty cards
- Monthly subscription via Stripe

### Subscription Flow

1. **Merchant visits `/billing`**
   - `BillingController::index()` shows usage stats
   - Displays current plan, usage meter, upgrade CTA

2. **Merchant clicks "Upgrade to Pro"**
   - `BillingController::checkout()` creates Stripe Checkout session
   - Redirects to Stripe Checkout page

3. **Merchant completes payment**
   - Stripe processes payment
   - Redirects to `/billing/success?session_id=...`

4. **Success page syncs subscription**
   - `BillingController::success()` retrieves session
   - Finds user and syncs subscription
   - Updates database

5. **Webhook processes events**
   - `POST /stripe/webhook` receives events
   - Handles: `checkout.session.completed`, `customer.subscription.*`
   - Syncs subscription status

### Grandfathering Logic

**Concept:** Cards created before subscription cancellation remain active.

**Implementation:**
- When subscription cancelled, `ends_at` is set
- Cards created before `ends_at` are "grandfathered"
- Only non-grandfathered cards count toward limit
- Grandfathered cards can still be stamped/redeemed

**Usage Service:**
```php
// Count only non-grandfathered cards
$nonGrandfatheredCount = LoyaltyAccount::whereIn('store_id', $storeIds)
    ->where('created_at', '>=', $subscription->ends_at)
    ->count();
```

### Billing Routes

- `GET /billing` - Overview page
- `GET /billing/checkout` - Create Stripe Checkout
- `GET /billing/portal` - Stripe Billing Portal
- `GET /billing/success` - Checkout success (syncs subscription)
- `POST /billing/sync` - Manual subscription sync

---

## 11. Wallet Integrations

### Apple Wallet Integration

#### Phase 1: Pass Generation

**Purpose:** Generate `.pkpass` files for customers to add to Apple Wallet.

**Implementation:**
- Service: `AppleWalletPassService`
- Package: `byte5/laravel-passgenerator`
- Route: `/wallet/apple/{token}/download` (signed URL)

**Pass Contents:**
- Barcode: `LA:{public_token}` or `LR:{redeem_token}` (dynamic)
- Display fields: Customer name, stamp count, reward balance
- Store branding: Logo, colors
- `webServiceURL`: `https://domain.com/wallet` (for auto-updates)
- `authenticationToken`: `wallet_auth_token` (per-pass auth)

**Serial Number Format:**
- `kawhe-{store_id}-{customer_id}`
- Example: `kawhe-1-2`

#### Phase 2: Auto-Updates (Pass Web Service)

**Purpose:** Automatically update passes when stamps/redeems occur.

**Endpoints:**

1. **Register Device**
   ```
   POST /wallet/v1/devices/{deviceId}/registrations/{passType}/{serial}
   ```
   - Stores `device_library_identifier` and `push_token`
   - Links to `loyalty_account_id`

2. **Get Updated Serials**
   ```
   GET /wallet/v1/devices/{deviceId}/registrations/{passType}?passesUpdatedSince=...
   ```
   - Returns serials that changed since timestamp
   - Filters by `loyalty_accounts.updated_at > passesUpdatedSince`
   - Returns Zulu format: `2026-01-20T21:52:23Z`

3. **Get Updated Pass**
   ```
   GET /wallet/v1/passes/{passType}/{serial}
   ```
   - Returns `.pkpass` file if updated
   - Returns `304 Not Modified` if unchanged
   - Includes `Last-Modified` header

4. **Unregister Device**
   ```
   DELETE /wallet/v1/devices/{deviceId}/registrations/{passType}/{serial}
   ```
   - Marks registration as inactive

5. **Log Endpoint**
   ```
   POST /wallet/v1/log
   ```
   - Receives diagnostic logs from Apple

**APNs Push Notifications:**

- When stamp/redeem occurs:
  1. `UpdateWalletPassJob` regenerates pass
  2. `WalletSyncService` calls `ApplePushService`
  3. `ApplePushService` sends APNs push to registered devices
  4. iPhone receives push and calls "Get Updated Serials"
  5. iPhone fetches updated pass

**APNs Configuration:**
- JWT authentication (ES256)
- Key file: `.p8` (from Apple Developer)
- Topic: `pass.com.kawhe.loyalty`
- Endpoint: Production or Sandbox (configurable)

**Authentication:**
- Per-pass: `wallet_auth_token` (from `loyalty_accounts`)
- Global fallback: `web_service_auth_token` (for `/log`)

### Google Wallet Integration

**Purpose:** Generate "Save to Google Wallet" links.

**Implementation:**
- Service: `GoogleWalletPassService`
- Package: `google/apiclient`
- Route: `/wallet/google/{token}/save` (signed URL)

**Process:**
1. Create/update `LoyaltyClass` (store-level template)
2. Create/update `LoyaltyObject` (customer-specific pass)
3. Generate JWT with save link
4. Redirect to Google Wallet

**Features:**
- Store branding (logo, colors)
- Dynamic barcode (stamp or redeem)
- Auto-updates via API

---

## 12. Deployment & Production

### Production Checklist

1. **Database Setup**
   - Use PostgreSQL or MySQL (not SQLite)
   - Run migrations: `php artisan migrate --force`
   - See: `docs/DATABASE_PRODUCTION_SETUP.md`

2. **Environment Configuration**
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`
   - Configure all services (Stripe, SendGrid, etc.)

3. **Queue Workers**
   - Setup Supervisor or systemd
   - Process `default` queue
   - Required for: Email, wallet updates

4. **Reverb Server**
   - Run as service (Supervisor/systemd)
   - Port 8080 (or configured port)
   - Required for real-time updates

5. **Web Server**
   - Nginx or Apache
   - PHP-FPM
   - SSL certificate required

6. **Caching**
   - Config cache: `php artisan config:cache`
   - Route cache: `php artisan route:cache`
   - View cache: `php artisan view:cache`

### Deployment Script

**Location:** `deploy-production.sh`

**Process:**
1. Git pull latest code
2. Install dependencies (`composer install --no-dev`)
3. Run migrations
4. Clear and cache config
5. Restart services (queue, reverb, web server)

### Safe Deployment Guide

**Location:** `docs/SAFE_DEPLOYMENT_GUIDE.md`

**Best Practices:**
- Test migrations on staging first
- Backup database before migrations
- Deploy during low-traffic periods
- Monitor logs after deployment
- Have rollback plan ready

---

## 13. Troubleshooting Guide

### Common Issues

#### Wallet Passes Not Updating

**Symptoms:** Passes in Apple Wallet don't update after stamping.

**Debug Steps:**
1. Check device registration: `php artisan wallet:check-registration`
2. Check APNs push: `php artisan wallet:apns-test {serial}`
3. Check logs: `tail -f storage/logs/laravel.log | grep -i push`
4. Verify `WALLET_APPLE_PUSH_ENABLED=true`
5. Verify APNs key file exists and is readable

**Common Causes:**
- Push notifications disabled
- Invalid APNs credentials
- Device not registered
- Queue worker not running

#### Subscription Not Syncing

**Symptoms:** Payment successful but subscription not active.

**Debug Steps:**
1. Check Stripe Dashboard for subscription
2. Check database: `SELECT * FROM subscriptions WHERE user_id = ?`
3. Try manual sync: `php artisan kawhe:sync-subscriptions {user_id}`
4. Check webhook logs in Stripe Dashboard
5. Verify `STRIPE_WEBHOOK_SECRET` in `.env`

#### Stamping Not Working

**Symptoms:** Scanner doesn't add stamps.

**Debug Steps:**
1. Check authentication (merchant logged in)
2. Check store access (merchant owns store)
3. Check rate limiting (cooldown)
4. Check logs: `tail -f storage/logs/laravel.log`
5. Verify database connection

#### Real-time Updates Not Working

**Symptoms:** Card page doesn't update when stamped.

**Debug Steps:**
1. Check Reverb server is running: `php artisan reverb:start`
2. Check browser console for WebSocket errors
3. Verify channel name matches: `loyalty-card.{public_token}`
4. Check event broadcasting: `tail -f storage/logs/laravel.log | grep StampUpdated`

### Debug Commands

```bash
# Check wallet registrations
php artisan wallet:check-registration [public_token]

# Test APNs push
php artisan wallet:apns-test {serial}

# Test JWT generation
php artisan wallet:test-jwt

# Sync subscriptions
php artisan kawhe:sync-subscriptions [user_id]

# Check queue status
php artisan queue:work --once
```

### Log Locations

- Application logs: `storage/logs/laravel.log`
- Queue logs: `queue.log` (if configured)
- Reverb logs: `reverb.log` (if configured)
- Server logs: `server.log` (if configured)

---

## Conclusion

This documentation covers the Kawhe Loyalty application from A to Z, including:

- **Architecture** - How the system is structured
- **Database** - All tables, models, and relationships
- **Features** - How each feature works
- **Implementation** - Code logic and services
- **API** - All endpoints and routes
- **Real-time** - WebSocket implementation
- **Security** - Data integrity mechanisms
- **Billing** - Subscription system
- **Wallet** - Apple and Google integrations
- **Deployment** - Production setup
- **Troubleshooting** - Common issues and solutions

For setup instructions, see the individual documentation files in the `docs/` folder.

---

**Last Updated:** January 2026  
**Version:** 2.0
