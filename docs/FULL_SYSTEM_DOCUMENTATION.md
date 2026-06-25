# Kawhe Full System Documentation

> **Handover:** For a concise onboarding guide for new developers, read [`DEVELOPER_HANDOVER.md`](DEVELOPER_HANDOVER.md) first.

## 1. Overview

Kawhe is a loyalty platform built around one Laravel backend that serves three connected experiences:

1. Merchant web app
- Merchant onboarding
- Store setup and branding
- Customer join QR/poster generation
- Customer management
- Scanner/stamp/redeem flows
- Billing and subscription management

2. Customer loyalty card experience
- Customer joins via QR code or short URL
- Customer gets a web loyalty card
- Customer can save the card to Apple Wallet or Google Wallet
- Card state updates when stamps or rewards change

3. Merchant mobile app integration
- A Flutter merchant app connects to this same Laravel backend via `/api/v1`
- It authenticates merchants, loads their stores, and uses scanner preview/stamp/redeem APIs

This means the system is not split across multiple backends. The web app, wallet services, and merchant mobile API are all part of the same Laravel codebase.

## 2. System Shape

At a high level, the platform works like this:

1. Merchant creates a store
2. Merchant configures branding, reward settings, and customer registration fields
3. Merchant shares a QR code / short join link
4. Customer joins a specific store's loyalty program
5. A loyalty account is created for that store/customer pair
6. Merchant scans the customer card to stamp or redeem
7. The backend updates loyalty state, logs the event, broadcasts real-time updates, and syncs wallet passes

## 3. Core Domain Model

### 3.1 `Store`
File: `/Users/robertcalvert/Desktop/kawhe 2.0/app/Models/Store.php`

A `Store` is the merchant's loyalty program configuration.

Responsibilities:
- Merchant ownership (`user_id` relationship)
- Store identity and address
- Reward rules
- Join URLs and short code
- Branding and pass assets
- Onboarding state
- Customer registration form configuration

Important fields:
- `name`
- `slug`
- `reward_target`
- `reward_title`
- `join_token`
- `join_short_code`
- `brand_color`
- `background_color`
- `logo_path`
- `pass_logo_path`
- `pass_hero_image_path`
- `require_verification_for_redemption`
- `registration_form_config`
- `onboarding_step`
- `onboarding_completed_at`

Important behavior:
- Auto-generates slug, join token, and short join code on create
- Exposes `join_url`
- Merges registration form config with sensible defaults
- Deletes related branding assets and generated wallet strip images when the store is deleted

### 3.2 `Customer`
File: `/Users/robertcalvert/Desktop/kawhe 2.0/app/Models/Customer.php`

A `Customer` is the real person in the system.

Responsibilities:
- Stores customer identity and contact info
- Can belong to many loyalty accounts across stores

Important fields:
- `name`
- `first_name`
- `last_name`
- `email`
- `phone`
- `birthday`
- `email_verified_at`

### 3.3 `LoyaltyProgram`
File: `app/Models/LoyaltyProgram.php`

A **loyalty card** is what customers actually join. Each store has one or more programs; exactly one is `is_default`.

Responsibilities:
- Own join URL (`slug`, `join_token`, `join_short_code`)
- Reward rules, branding, wallet assets, registration form config per card
- Soft-delete (archive) while preserving customer history

Important fields:
- `store_id`, `name`, `slug`, `is_default`
- `reward_target`, `reward_title`
- `join_token`, `join_short_code`
- `brand_color`, `background_color`, `logo_path`, `pass_logo_path`, `pass_hero_image_path`
- `registration_form_config`, `require_verification_for_redemption`

Customer join resolves **program** slug + token first; legacy store slug/token fall back to the store’s default program.

### 3.4 `LoyaltyAccount`
File: `app/Models/LoyaltyAccount.php`

A `LoyaltyAccount` is the customer's loyalty membership for **one loyalty program** (one card).

This is the main stateful record of the loyalty program.

Responsibilities:
- Current stamp count
- Current reward balance
- Public card token
- Redeem token
- Wallet auth token
- Manual entry code
- Program-specific email verification state
- Versioning / optimistic-state tracking

Important fields:
- `store_id`, `loyalty_program_id`, `customer_id`
- `stamp_count`
- `reward_balance`
- `public_token`
- `wallet_auth_token`
- `redeem_token`
- `manual_entry_code`
- `last_stamped_at`
- `reward_available_at`
- `reward_redeemed_at`
- `verified_at`
- `version`

Important behavior:
- Auto-generates `public_token`, `wallet_auth_token`, and `manual_entry_code`
- Unique per `(loyalty_program_id, customer_id)` — one customer can hold multiple cards across programs
- Apple Wallet serial: `kawhe-{loyalty_account_id}` (legacy `kawhe-{store_id}-{customer_id}` still resolves)
- Deletes generated Google Wallet stamp-strip assets when deleted
- Supports route-notification email delivery using the linked customer email

### 3.5 `StampEvent`
File: `/Users/robertcalvert/Desktop/kawhe 2.0/app/Models/StampEvent.php`

This is the audit/event table for scanner actions.

Responsibilities:
- Tracks stamp and redeem events
- Stores idempotency keys
- Stores user agent / IP for auditing

### 3.6 `PointsTransaction`
File: `/Users/robertcalvert/Desktop/kawhe 2.0/app/Models/PointsTransaction.php`

This is the ledger table.

Responsibilities:
- Logs earning and redemption point movements
- Provides audit trail and transaction history
- Powers card transaction history APIs/views

Important fields:
- `type` (`earn`, `redeem`)
- `points`
- `idempotency_key`
- `metadata`

## 4. Main Functional Areas

### 4.1 Merchant Web App

Key routes are defined in:
- `/Users/robertcalvert/Desktop/kawhe 2.0/routes/web.php`

Main merchant features:
- Onboarding wizard
- Dashboard
- Store CRUD
- QR/join poster generation
- Scanner UI
- Merchant customer views
- Billing

### 4.2 Customer Join + Card Experience

Customer-facing flows:
- Join landing
- New registration
- Existing-customer lookup
- Card page
- Wallet add flows
- Email verification for redemption if enabled

### 4.3 Wallet Integrations

Two wallet systems are supported:
- Apple Wallet
- Google Wallet

These are synchronized from the same `LoyaltyAccount` state.

### 4.4 Merchant Mobile App API

The Flutter merchant app uses the authenticated API under `/api/v1`.

Routes are defined in:
- `/Users/robertcalvert/Desktop/kawhe 2.0/routes/api.php`

## 5. Route Map

### 5.1 Public / Customer Routes
- `/start` - merchant onboarding landing
- `/` - app landing page
- `/j/{code}` - short join URL redirect
- `/join/{slug}` - join landing page
- `/join/{slug}/new` - new customer join page + submit
- `/join/{slug}/existing` - existing customer recovery page + submit
- `/c/{public_token}` - customer card page
- `/api/card/{public_token}` - customer card JSON API
- `/api/card/{public_token}/transactions` - customer card transaction history

### 5.2 Wallet Routes
- Apple Wallet pass generation/download routes
- Google Wallet save-link routes
- Apple Wallet web service routes under `/wallet/v1/...`
- Email verification routes for loyalty redemption

### 5.3 Merchant Authenticated Web Routes
- `/merchant/dashboard`
- `/merchant/stores/*`
- `/merchant/scanner`
- `/merchant/customers/*`
- `/billing/*`

### 5.4 Merchant Mobile API Routes
Files:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Http/Controllers/Api/AuthController.php`
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Http/Controllers/Api/StoreController.php`

Endpoints:
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `GET /api/v1/stores`
- `POST /api/v1/scanner/preview`
- `POST /api/v1/stamp`
- `POST /api/v1/redeem/info`
- `POST /api/v1/redeem`

## 6. Merchant Onboarding Flow

Controller:
- `app/Http/Controllers/MerchantOnboardingWizardController.php`

Support:
- `app/Support/StoreBrandingRules.php` — required colors + logo/wallet assets
- `app/Support/RegistrationFormConfig.php` — join form field config from requests
- `Store::syncDefaultProgramFromStore()` — keeps default program aligned with store during wizard

The onboarding wizard is a **4-step** setup flow for a merchant's first store. Uses `onboarding-layout` (no sidebar).

Steps:
1. `store_basics` — name, address, reward target/title
2. `card_design` — brand colors, store logo, wallet logo, wallet hero (**all required**)
3. `customer_form` — registration field toggles via shared `registration-form-config-editor` component
4. `card_ready` — join link/QR preview; complete onboarding → store QR page

State tracking:
- `stores.onboarding_step`, `stores.onboarding_completed_at`

Legacy:
- `POST /merchant/onboarding/store` redirects to wizard (no direct store create)
- `continue_trial` step removed; route redirects to `card_ready`

Registration (`RegisteredUserController`) creates user + store + default program and redirects to wizard step 1.

## 7. Store Creation / Editing / Loyalty Cards

Controllers:
- `app/Http/Controllers/StoreController.php` — stores CRUD; create uses `StoreBrandingRules`
- `app/Http/Controllers/LoyaltyProgramController.php` — loyalty cards (programs) per store

Responsibilities:
- Create and update stores and programs
- Validate reward configuration and **required** branding uploads on create/onboarding
- Persist `registration_form_config` via `RegistrationFormConfig::fromRequest()`
- Generate QR and poster views (store and per-program)
- Archive/restore programs (soft delete)

Important notes:
- Asset uploads via `StoreAssets`; replacing images deletes old assets
- Program reward target locks after customers join
- Free plan limits enforced by `UsageService::canCreateProgram()`
- Default program synced from store during onboarding wizard

## 8. Customer Join Flow

Controller:
- `app/Http/Controllers/JoinController.php`

### 8.1 Entry
The customer starts from either:
- short join URL `/j/{code}` (program or store default program)
- full join URL `/join/{slug}?t={join_token}` (program slug + token; legacy store tokens supported)

Invalid links → branded `join.invalid` (404). Archived store/program → `join.archived`.

### 8.2 Landing
Landing validates program (or store default) slug + token. Primary CTA: get new card; secondary: find existing card.

### 8.3 New Join
Form fields from **`program.registration_form_config`**.

Behavior:
- Legacy `name` field still supported
- Match/create `Customer` by email, then phone
- Unique account per `(loyalty_program_id, customer_id)`
- If account exists for program → redirect to existing card
- Welcome / verification email when customer has email
- Session: `registered`, `show_wallet_nudge` (first visit only)

Submit shows loading state on join form.

### 8.4 Existing Customer Recovery
Route: `POST join.lookup` (10/min throttle).

Behavior:
- **Email** lookup always available
- If program has **phone enabled** in form config → email **or** phone (at least one)
- Finds customer, then loyalty account for **this program only**
- Redirects to card page or field-specific error
- Submit loading state + `<x-input-error>` styling

## 9. Customer Card Flow

Controller:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Http/Controllers/CardController.php`

The card page is the customer-facing loyalty card.

Responsibilities:
- Show card UI
- Reconcile reward/redeem token state
- Provide JSON API for the card UI
- Provide transaction history
- Provide web app manifest for install/PWA behavior

Important behavior:
- Ensures `redeem_token` exists when reward balance is available
- Clears stale redeemed state when a customer has continued stamping again
- Returns card state including reward availability, customer name, reward title, and token status

## 10. Scanner / Stamp / Redeem Flow

Controller:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Http/Controllers/ScannerController.php`

Service:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Services/Loyalty/StampLoyaltyService.php`

This is one of the most important parts of the system.

### 10.1 Token Types
The scanner supports several token forms:
- `LA:{public_token}` for stamp-mode QR
- `LR:{redeem_token}` or `REDEEM:{redeem_token}` for redeem-mode QR
- 4-character manual entry code
- plain `public_token`
- plain `redeem_token`

### 10.2 Preview Flow
`POST /api/v1/scanner/preview`

Purpose:
- Inspect the scanned token before action
- Determine whether the customer has rewards available
- Determine whether the QR is a stamp QR or redeem QR
- Confirm store ownership/access

Response includes:
- reward balance
- reward title
- stamp count
- reward target
- customer name
- store name
- store ID
- whether it is a redeem QR
- both public token and redeem token where helpful for UI switching

### 10.3 Stamp Flow
`POST /stamp` or `POST /api/v1/stamp`

Behavior:
- Validates token and store access
- Resolves manual codes and prefixed QR tokens
- Supports client idempotency key
- Enforces cooldown rules
- Enforces hard duplicate-scan suppression window
- Delegates stamp transaction to `StampLoyaltyService`
- Returns receipt-like response with updated counts

### 10.4 Redemption Flow
`POST /redeem/info`
- looks up reward balance and verification requirements

`POST /redeem`
- validates store ownership
- validates reward availability
- validates requested redemption quantity
- checks store-specific verification requirement
- decrements `reward_balance`
- rotates or clears `redeem_token`
- logs redemption to `PointsTransaction`
- dispatches real-time and wallet update work

### 10.5 Inactive / Missing Card Handling
The scanner has a stable inactive-card response:
- HTTP `404`
- `code = CARD_NOT_ACTIVE`
- message tells merchant the card is no longer active

This is important for deleted accounts or deleted stores whose old passes may still exist on customer devices.

## 11. Stamping Service Details

Service:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Services/Loyalty/StampLoyaltyService.php`

Responsibilities:
- Validate merchant access to store/account
- Apply stamps in a transaction
- Use row locking for concurrency safety
- Use `StampEvent` for idempotency and auditing
- Update reward balance when threshold is crossed
- Ensure `redeem_token` exists when rewards are available
- Create ledger entry in `PointsTransaction`
- Dispatch wallet update job after commit
- Broadcast `StampUpdated`

The service is designed to be safe against:
- duplicate client submissions
- concurrent scans
- wallet update failures breaking the scanner response

## 12. Real-Time Updates

Event:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Events/StampUpdated.php`

Behavior:
- Broadcasts immediately (`ShouldBroadcastNow`)
- Channel: `loyalty-card.{public_token}`
- Payload contains current stamps, rewards, store name, reward title, token state, and customer name

Purpose:
- Keep open card views in sync in real time
- Power live loyalty card updates in the browser

Infrastructure:
- Laravel Reverb is used for real-time transport

## 13. Wallet System

## 13.1 Wallet Sync Orchestrator
Service:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Services/Wallet/WalletSyncService.php`

Responsibilities:
- Load latest loyalty account state
- Trigger Apple Wallet push notifications
- Trigger Google Wallet object updates
- Log failures without breaking the main scanner flow

This service is called asynchronously through a queue job.

## 13.2 Wallet Update Job
Job:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Jobs/UpdateWalletPassJob.php`

Responsibilities:
- Load account by ID
- Call `WalletSyncService`
- Retry on failure
- Log final failures

## 13.3 Apple Wallet
Service:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Services/Wallet/AppleWalletPassService.php`

Responsibilities:
- Build Apple `.pkpass` payload
- Embed current QR token state
- Build front and back pass fields
- Use store branding for colors/logo/hero image
- Provide fallback assets if store assets are missing
- Attach wallet web service configuration for update notifications

Important Apple behaviors:
- Uses `wallet_auth_token` as the wallet authentication token
- Uses centralized serial number generation
- Uses reward-aware QR mode:
  - normal mode -> `LA:{public_token}`
  - redeem mode -> `LR:{redeem_token}`
- Uses pass assets like `logo.png`, `strip.png`, `icon.png`, `background.png`
- Apple updates are triggered through APNs push notifications and pass web service registration flow

## 13.4 Google Wallet
Service:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Services/Wallet/GoogleWalletPassService.php`

Responsibilities:
- Create/update Google Wallet class and object records
- Apply store branding, images, hero images, and background colors
- Keep Google Wallet object synchronized with current account reward/stamp state
- Support generic pass rendering path and loyalty object rendering path

Important behaviors:
- Uses a Google service account JSON key
- Ensures pass class exists before object updates
- Applies store-specific branding to both class and object
- Uses generated visual stamp-strip assets for the Google front-card design

## 14. Asset Storage and Branding

Support layer:
- `StoreAssets`

Public assets include:
- store logos
- pass logos
- pass hero images
- generated Google Wallet stamp-strip images

Current storage model:
- Public assets are served from DigitalOcean Spaces
- Asset URLs are used in web views and Google Wallet rendering
- Apple Wallet bundles images into the pass package at generation time

Cleanup behavior:
- Replacing store assets deletes old ones
- Deleting a store deletes owned brand assets and generated stamp strips
- Deleting a loyalty account deletes generated stamp strips for that account

## 15. QR / Poster Generation

Controller:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Http/Controllers/StoreController.php`

Features:
- Store-specific join QR view
- A4 poster generation as HTML or PDF
- Uses store branding and wallet badges
- Generates QR codes dynamically from join URLs

The poster is meant for print and customer onboarding in-store.

## 16. Billing and Subscription Model

Controller:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Http/Controllers/BillingController.php`

Service:
- `/Users/robertcalvert/Desktop/kawhe 2.0/app/Services/Billing/UsageService.php`

### 16.1 Free Plan
- Free merchants can create up to 50 loyalty cards

### 16.2 Paid Plan
- Subscription removes the card limit
- Stripe Checkout and Cashier are used

### 16.3 Usage Rules
`UsageService` handles:
- card counting across all merchant stores
- free-limit enforcement
- subscription status checks
- grandfathering logic for cancelled subscriptions

Grandfathering behavior:
- If a merchant cancels, cards created before cancellation may remain active
- New cards created after cancellation count toward the free plan rules

## 17. Merchant Mobile App Integration

The merchant mobile app lives outside this repo, but it uses this backend directly.

Known integration shape:
- Mobile app authenticates using `POST /api/v1/auth/login`
- Stores token using Laravel Sanctum token auth
- Loads merchant-owned stores via `GET /api/v1/stores`
- Uses scanner APIs for preview/stamp/redeem
- Uses returned store branding (`brand_color`, `background_color`, `logo_url`) to theme the mobile UI per selected store

This is important architecturally:
- the mobile app does not own loyalty logic
- the mobile app is a client of the same scanner and store APIs used by the web system

### 17.1 Mobile App Expected Flow
1. Merchant logs in
2. App fetches stores
3. Merchant selects a store
4. App scans QR or manual code
5. App calls preview API
6. App decides whether to stamp or redeem
7. App calls stamp or redeem endpoint
8. App shows result and cooldown UI
9. Backend updates loyalty state and wallets

### 17.2 Mobile App Error Model
The backend already supports stable scanner errors used by the mobile app, including:
- bad credentials
- access denied to store
- card not active
- verification required
- cooldown / duplicate scan responses

## 18. Security / Integrity Patterns

The codebase uses several important integrity patterns:

1. Idempotency keys
- Prevent duplicate stamp/redeem processing

2. Row locking in stamping/redeeming
- Protect against concurrent scanner actions

3. Store ownership checks
- Merchant cannot stamp or redeem another merchant's accounts

4. Wallet auth token separation
- Wallet auth token is different from public card token

5. Store-specific verification checks
- Reward redemption verification is enforced at the store/account layer, not globally

## 19. Frontend Structure

Frontend is primarily Blade + Tailwind + Alpine.

Main frontend groups:
- onboarding wizard views
- store create/edit views
- join flow views
- card views
- scanner view
- billing views

Patterns used:
- Blade server rendering for most pages
- JSON endpoints for card and mobile/scanner flows
- Alpine for richer live previews and form UI
- Tailwind for styling

## 20. Queue / Async Work

Important async jobs and background work:
- wallet update job after stamp/redeem
- welcome/verification emails
- Stripe webhook processing / Cashier sync flows

Operationally, this app depends on a running queue worker in production.

## 21. Realtime / WebSocket Layer

Realtime updates are powered by:
- Laravel broadcasting
- Reverb

Primary known broadcast use:
- loyalty card update channel after stamp/redeem

## 22. Operational Notes

Production setup currently includes:
- Laravel app on server
- queue worker
- Reverb
- Nginx proxying
- backups and health checks
- Discord webhook alerts
- public asset storage in DigitalOcean Spaces
- DB backups uploaded to Spaces

These are not just ops details. They matter to how wallet updates, scanner stability, and customer card freshness behave.

## 23. Critical Paths

### 23.1 New Merchant Path
1. Merchant registers/logs in
2. Onboarding creates first store
3. Merchant configures branding and form
4. Merchant gets join QR/poster

### 23.2 New Customer Path
1. Customer scans join QR
2. Opens join page
3. Submits form
4. `Customer` is created or matched
5. `LoyaltyAccount` is created
6. Customer lands on card page
7. Customer optionally adds wallet pass

### 23.3 Stamp Path
1. Merchant scans customer pass
2. Preview resolves token/account/store
3. Merchant confirms stamp
4. `StampLoyaltyService` updates account
5. `StampEvent` + `PointsTransaction` are written
6. `StampUpdated` broadcasts
7. `UpdateWalletPassJob` runs
8. Apple/Google passes sync

### 23.4 Redeem Path
1. Merchant scans redeem QR or manual code
2. Preview/redeem-info confirms reward balance
3. Verification requirement is checked
4. Reward is redeemed in transaction
5. Ledger/event records written
6. Real-time + wallet sync triggered

## 24. Where To Edit What

### Branding and pass visuals
- `StoreController`
- onboarding views
- wallet pass services
- `StoreAssets`

### Scanner behavior
- `ScannerController`
- `StampLoyaltyService`
- mobile app client behavior (in the Flutter repo)

### Card/customer-facing UI
- `CardController`
- join views
- card views
- broadcast event payloads

### Billing and plan rules
- `BillingController`
- `UsageService`

### Wallet behavior
- `AppleWalletPassService`
- `GoogleWalletPassService`
- `WalletSyncService`
- `UpdateWalletPassJob`

## 25. Summary

This app is a single-system loyalty platform with:
- one backend
- one merchant web app
- one customer loyalty card experience
- one merchant mobile API surface
- two wallet integrations

The system revolves around:
- `Store` as the configured loyalty program
- `Customer` as the person
- `LoyaltyAccount` as the store-specific card/state container

The most important technical flows are:
- onboarding
- join/create card
- stamp/redeem
- wallet sync
- billing limits

The mobile app is not separate business logic. It is another client of the same backend scanner/store/auth APIs.

## 26. Recommended Next Documentation Additions

If needed later, this document can be expanded with:
1. ERD / database schema diagram
2. request/response examples for every scanner endpoint
3. deployment runbook
4. wallet credential/config setup guide
5. Flutter merchant app code-level integration document
