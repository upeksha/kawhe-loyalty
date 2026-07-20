# Kawhe Full System Documentation

**Last updated:** July 20, 2026
**Current app state:** Single standard wallet card visual style. The previous Abstract/card-type experiment has been removed.
**Audience:** Owner, operators, support, and engineers who need to understand how the app works end to end.

This document explains how Kawhe works from merchant setup through customer joining, digital wallet pass creation, scanner operations, billing, support, admin diagnostics, and mobile app integration.

For a shorter engineer handover, also read `docs/DEVELOPER_HANDOVER.md`.

---

## 1. Product Summary

Kawhe is a self-serve SaaS loyalty platform for merchants such as cafes and retail stores.

The app lets a merchant:

- Register and create a store.
- Configure a loyalty card reward rule, branding, wallet images, and customer join form.
- Share a QR code or join link with customers.
- Let customers join without downloading an app.
- Let customers save their loyalty card to Apple Wallet or Google Wallet.
- Scan customer cards to add stamps or redeem rewards.
- View customers, card progress, recent activity, wallet state, and support logs.
- Manage plan and billing through Stripe.

The app lets a customer:

- Scan a merchant QR code.
- Join a loyalty card with email and optional fields configured by the merchant.
- See their web loyalty card.
- Save the card to Apple Wallet or Google Wallet.
- Show the card QR/manual code at checkout to collect stamps or redeem rewards.
- Verify their email if the merchant requires verification before redemption.

The same Laravel backend powers:

- Merchant web dashboard.
- Customer join/card web pages.
- Apple Wallet pass downloads and Apple Wallet web-service updates.
- Google Wallet save-link/object updates.
- Merchant mobile app API under `/api/v1`.
- Admin dashboard and support diagnostics.

---

## 2. Technology Stack

| Area | Current implementation |
| --- | --- |
| Backend | Laravel 12, PHP 8.2+ |
| Web auth | Laravel Breeze session auth |
| Mobile auth | Laravel Sanctum API tokens |
| Frontend | Blade, Tailwind CSS, Alpine.js, Vite |
| Dashboard charts | Chart.js initialized from Blade data attributes |
| Billing | Laravel Cashier + Stripe Checkout + Stripe Billing Portal |
| Queue jobs | Laravel queues, `UpdateWalletPassJob` for wallet sync |
| Realtime | Laravel Reverb and Echo for card/stamp updates |
| Wallets | Apple PassKit `.pkpass`, Google Wallet API |
| Assets | `StoreAssets` using configured `filesystems.assets_disk` |
| Tests | Pest / PHPUnit |

Common local commands:

```bash
composer install
npm install
php artisan migrate
php artisan serve --port=8000
npm run dev
php artisan test
npm run build
```

Production/testing deploys use the scripts in `ops/`, for example `ops/deploy-testing.sh` on the testing server.

---

## 3. Current Product Boundaries

### 3.1 One wallet card visual style

The app currently has one standard wallet card style. Merchants do not choose between card types.

Wallet card customisation currently uses:

- Brand color.
- Background color.
- Store/customer-card logo.
- Wallet logo.
- Wallet hero image.
- Reward title.
- Reward target.
- Join form fields.
- Email verification setting.

The following options are intentionally not present in the current app state:

- Abstract icon card type.
- Wallet card type selector.
- Background pattern selector.
- Pattern color selector.
- Custom uploaded stamp icon.

### 3.2 Loyalty cards vs wallet card style

The word "card" is used in two related ways:

- **Loyalty card / loyalty program:** A merchant reward program customers join, such as "Buy 8, get 1 free". Paid plans can run multiple loyalty cards per store.
- **Wallet card visual style:** The visual pass layout used in Apple Wallet and Google Wallet. There is currently only one standard visual style.

This distinction matters. The app can support multiple loyalty programs per store based on billing limits, but all of them use the same standard wallet rendering method.

---

## 4. Core Domain Model

### 4.1 Merchant user

Model: `app/Models/User.php`

A merchant user owns stores and logs into the merchant control panel. A user can also be marked as a super admin with `is_super_admin`.

Merchant users can:

- Manage their own stores.
- Manage loyalty cards/programs under those stores.
- Scan cards for their own stores.
- View their own customers and support logs.
- Manage billing.

Super admins can:

- Access `/admin/dashboard`.
- Access `/admin/support`.
- View platform-level diagnostics.

### 4.2 Store

Model: `app/Models/Store.php`

A store represents a merchant location or merchant workspace container.

Important responsibilities:

- Store identity: name, slug, address.
- Merchant ownership through `user_id`.
- Onboarding state.
- Default loyalty program relationship.
- Legacy/fallback reward and branding fields.
- Store-level QR and launch readiness panels.
- Archive/restore behavior.

Important fields:

- `name`
- `slug`
- `address`
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
- `default_loyalty_program_id`
- `deleted_at`

Important behavior:

- Generates slug, join token, and short join code on create.
- Ensures a default `LoyaltyProgram` exists.
- Syncs onboarding store fields to the default program during onboarding.
- Uses soft delete for archive flows.
- Deletes image assets only on force delete.

### 4.3 LoyaltyProgram

Model: `app/Models/LoyaltyProgram.php`

A loyalty program is what the merchant UI calls a **Loyalty Card**.

Each loyalty program has its own:

- Join URL.
- Short QR code.
- Reward target.
- Reward title.
- Branding colors.
- Store logo.
- Wallet logo.
- Wallet hero image.
- Customer join form config.
- Redemption verification setting.

Important fields:

- `store_id`
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
- `sort_order`
- `is_default`
- `deleted_at`

Important behavior:

- Generates slug, join token, and short code on create.
- Has a public `join_url` accessor.
- Can be archived/restored using soft delete.
- The default program is created with the store.
- Reward target is locked after customers have joined that program.

### 4.4 Customer

Model: `app/Models/Customer.php`

A customer is the person who joined one or more loyalty cards.

Important fields:

- `name`
- `first_name`
- `last_name`
- `email`
- `phone`
- `birthday`
- `email_verified_at`

The app can match an existing customer by email, and by phone when phone lookup is enabled for that program.

### 4.5 LoyaltyAccount

Model: `app/Models/LoyaltyAccount.php`

A loyalty account is one customer's membership in one loyalty program.

Important fields:

- `store_id`
- `loyalty_program_id`
- `customer_id`
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
- `email_verification_token_hash`
- `email_verification_expires_at`
- `email_verification_sent_at`
- `version`

Important behavior:

- Generates a public card token.
- Generates a separate wallet auth token for Apple Wallet web service security.
- Generates a 4-character manual entry code per store.
- Resolves program settings from `loyalty_program_id`, falling back to the store default program if needed.
- Deletes generated Google Wallet stamp-strip images when the account is deleted.

### 4.6 StampEvent

Model: `app/Models/StampEvent.php`

Audit/event record for scanner actions.

Used for:

- Stamp history.
- Redeem history.
- Idempotency checks.
- Merchant/admin activity timelines.
- Dashboard trends.

### 4.7 PointsTransaction

Model: `app/Models/PointsTransaction.php`

Ledger-style transaction record for earn/redeem movements.

Used for:

- Reward earned calculations.
- Reward redeemed calculations.
- Customer card transaction history.
- Merchant analytics.

### 4.8 SupportAuditLog

Model: `app/Models/SupportAuditLog.php`

Support/audit log for wallet sync attempts, billing issues, verification sends, welcome email sends, manual support actions, and store wallet refresh requests.

Used by:

- Merchant support logs.
- Customer detail support timeline.
- Admin support diagnostics.
- Admin dashboard repeated-issue detection.

---

## 5. Merchant Control Panel

Main layout: `resources/views/layouts/merchant.blade.php` and `resources/views/components/merchant-layout.blade.php`

Primary merchant navigation:

- Dashboard.
- Stores.
- Cards.
- Customers.
- Scanner.
- Billing.
- Profile/logout.

Support logs are available at `/merchant/support` and are linked from wallet/support areas.

### 5.1 Dashboard

Route: `GET /merchant/dashboard`
View: `resources/views/dashboard.blade.php`
Analytics service: `app/Services/Analytics/MerchantAnalyticsService.php`

The merchant dashboard shows:

- Quick actions for scanner, store QR, and customers.
- Active customers.
- Joins in the recent window.
- Rewards earned.
- Rewards redeemed.
- Loyalty activity chart.
- Card growth chart.
- Store readiness and wallet readiness summaries.
- Recent activity.

Analytics are built from:

- `LoyaltyAccount` joins and activity timestamps.
- `StampEvent` stamp/redeem activity.
- `PointsTransaction` reward earned/redeemed metadata.

### 5.2 Stores

Routes:

- `GET /merchant/stores`
- `GET /merchant/stores/create`
- `POST /merchant/stores`
- `GET /merchant/stores/{store}/edit`
- `PUT /merchant/stores/{store}`
- `DELETE /merchant/stores/{store}`
- `POST /merchant/stores/{store}/restore`
- `GET /merchant/stores/{store}/qr`
- `GET /merchant/stores/{store}/qr/pdf`
- `GET /merchant/stores/{store}/qr/image`
- `POST /merchant/stores/{store}/refresh-wallets`

Controller: `app/Http/Controllers/StoreController.php`

Merchants can:

- View active and archived stores.
- Create stores within plan limits.
- Edit store name and address.
- View default loyalty card information from the store edit page.
- Open the default card editor from the store edit page.
- See wallet health and launch quality indicators.
- Queue wallet refresh for all cards in the store.
- Archive a store instead of hard deleting it.
- Restore an archived store.
- View/download the store QR and poster.

The Store edit page treats Store as the location and operational container. Reward settings, registration fields, colours, and Wallet images are managed on the associated loyalty card. Legacy Store branding values remain in the database for onboarding and compatibility and are shown only as read-only fallback information after onboarding.

Archive behavior:

- New joins and QR sharing are paused.
- Customer records and history are preserved.
- Wallet cards may remain on customer phones.
- Restore reactivates joins and QR sharing.

Store creation requires these assets through `StoreBrandingRules`:

- `brand_color`
- `background_color`
- store `logo`
- `pass_logo`
- `pass_hero_image`

Accepted image formats:

- PNG.
- JPG/JPEG.
- WebP.
- Max 2 MB.

### 5.3 Cards / Loyalty Programs

Routes:

- `GET /merchant/programs`
- `GET /merchant/stores/{store}/programs`
- `GET /merchant/stores/{store}/programs/create`
- `POST /merchant/stores/{store}/programs`
- `GET /merchant/stores/{store}/programs/{program}/edit`
- `PUT /merchant/stores/{store}/programs/{program}`
- `DELETE /merchant/stores/{store}/programs/{program}`
- `POST /merchant/stores/{store}/programs/{program}/restore`
- `GET /merchant/stores/{store}/programs/{program}/qr`
- `GET /merchant/stores/{store}/programs/{program}/qr/pdf`
- `GET /merchant/stores/{store}/programs/{program}/qr/image`

Controller: `app/Http/Controllers/LoyaltyProgramController.php`

A loyalty program is the customer-facing loyalty card.

Merchants can:

- View cards grouped by store.
- Create additional cards if the plan allows it.
- Edit card name.
- Edit reward target before customers join.
- Edit reward title.
- Edit card brand color/background color.
- Upload card-specific logo, wallet logo, and wallet hero image.
- Configure whether email verification is required before redemption.
- Configure customer join form fields.
- View card QR and poster.
- Archive non-default cards.
- Restore archived cards.

Important behavior:

- Each card has its own join link and QR code.
- Customers who join one card keep progress separate from other cards.
- Reward target locks after customers have joined that card. This prevents silently changing progress rules for existing customers.
- The default card cannot be archived from the cards screen.
- New card creation can inherit store images if no card-specific images are uploaded.

### 5.4 Customer Join Form Config

Code: `app/Support/RegistrationFormConfig.php`
Component: `resources/views/components/registration-form-config-editor.blade.php`

Email is always enabled and required.

Optional fields:

- First name.
- Last name.
- Phone.
- Birthday.

For each optional field, a merchant can choose:

- Enabled or disabled.
- Required or optional.

If phone is enabled, existing-card lookup can use email or phone. If phone is disabled, lookup is email-only.

### 5.5 Customers

Routes:

- `GET /merchant/customers`
- `GET /merchant/customers/export`
- `GET /merchant/customers/{loyaltyAccount}`
- `GET /merchant/customers/{loyaltyAccount}/edit`
- `PUT /merchant/customers/{loyaltyAccount}`
- `POST /merchant/customers/{loyaltyAccount}/resend-verification`
- `POST /merchant/customers/{loyaltyAccount}/resend-welcome`
- `POST /merchant/customers/{loyaltyAccount}/sync-wallet`

Controller: `app/Http/Controllers/MerchantCustomersController.php`

Merchants can:

- Search customers.
- Filter customers by store.
- View customer loyalty card details.
- See stamps, reward balance, reward target, manual code, and verification state.
- Edit customer contact details.
- Export customers to CSV on Pro.
- Resend verification email.
- Resend welcome email.
- Queue a wallet sync for an individual customer card.
- View support timeline for wallet registrations, verification sends, stamp/redeem activity, and support actions.

CSV export includes:

- Store.
- Loyalty card.
- First name.
- Last name.
- Full name.
- Email.
- Phone.
- Birthday.
- Manual code.
- Stamps.
- Reward target.
- Reward status.
- Email verified.
- Last stamped.
- Joined date.

### 5.6 Scanner

Routes:

- `GET /merchant/scanner`
- `POST /scanner/preview`
- `POST /stamp`
- `POST /redeem/info`
- `POST /redeem`

Controller: `app/Http/Controllers/ScannerController.php`
Stamp service: `app/Services/Loyalty/StampLoyaltyService.php`

The scanner supports:

- Web camera QR scanning as a backup scanner.
- Mobile app download promotion for easier scanning.
- Store selection.
- Previewing a scanned card before action.
- Adding one or more stamps.
- Redeeming one or more rewards.
- Manual entry by 4-character manual code.
- Handling stamp QR tokens and redeem QR tokens.
- Cooldown and duplicate-scan protection.
- Idempotency keys from clients.

Accepted token formats:

- `LA:{public_token}` for stamp card QR.
- `LR:{redeem_token}` for reward redemption QR.
- `REDEEM:{redeem_token}` legacy redemption format.
- Plain public token.
- Plain redeem token.
- 4-character manual code when a store is selected.

Stamp behavior:

- Checks merchant access to the card's store.
- Locks the loyalty account row in a transaction.
- Adds stamp count.
- Rolls over stamps into reward balance when target is reached.
- Creates stamp event and points transaction.
- Queues wallet sync after transaction commit.
- Broadcasts realtime update.

Redeem behavior:

- Checks merchant access to the selected store.
- Checks reward balance.
- Checks email verification if required.
- Deducts reward balance.
- Writes event/ledger history.
- Queues wallet sync.

### 5.7 Billing

Routes:

- `GET /billing`
- `POST /billing/checkout`
- `POST /billing/portal`
- `GET /billing/success`
- `POST /billing/sync`
- `GET /billing/cancel`
- `POST /stripe/webhook`

Controller: `app/Http/Controllers/BillingController.php`
Usage service: `app/Services/Billing/UsageService.php`
Plan config: `config/billing.php`

The billing page is intentionally simple:

- One hero status section.
- One plan comparison section.
- One usage card area.
- Main action: upgrade, manage subscription, or refresh status.

Plans:

| Plan | Stores | Loyalty cards per store | Customers per card |
| --- | ---: | ---: | ---: |
| Free | 1 | 1 | 100 |
| Pro | 3 | 5 | Unlimited |
| Business | Unlimited | Unlimited | Unlimited, not sold yet |

Billing behavior:

- Free users can run 1 store, 1 card, and 100 customers on that card.
- Pro unlocks up to 3 stores, 5 loyalty cards per store, and unlimited customers.
- Existing stores, cards, and customers are not deleted when limits change.
- Limits block new growth, not existing access.
- Checkout creates a Stripe Checkout subscription session.
- Billing portal lets subscribed users manage payment method, invoices, and subscription.
- Success page tries to sync the Stripe subscription immediately.
- Manual sync can recover after Stripe/webhook timing delays or Stripe account changes.
- Billing issues are written to support audit logs.

### 5.8 Support Logs

Merchant route: `GET /merchant/support`
Admin route: `GET /admin/support`
Controller: `app/Http/Controllers/SupportLogController.php`

Merchant support logs show events only for stores owned by the merchant.

Merchants can filter by:

- Store.
- Event type.
- Status.
- Search term for customer email/name, public token, or manual code.

Admin support logs show platform-wide support events.

Admin can filter by:

- Issues only.
- Event type.
- Status.
- Store.
- Search term.

Common event types include:

- `wallet_sync`
- `billing_issue`
- `verification_send`
- `welcome_email_send`
- `manual_support_action`
- `store_wallet_refresh`

---

## 6. Merchant Onboarding Flow

Routes are under `/merchant/onboarding/wizard`.

Controller: `app/Http/Controllers/MerchantOnboardingWizardController.php`

Onboarding is separate from the normal merchant sidebar layout. It is designed to force a new merchant through the minimum setup needed before they can launch.

### Step 1: Store basics

Route:

- `GET /merchant/onboarding/wizard/store-basics`
- `POST /merchant/onboarding/wizard/store-basics`

Collects:

- Store name.
- Address.
- Reward target.
- Reward title.

Creates or updates the onboarding store, creates the default loyalty program, and moves to card design.

### Step 2: Card design

Route:

- `GET /merchant/onboarding/wizard/card-design`
- `POST /merchant/onboarding/wizard/card-design`

Collects required wallet/branding assets:

- Brand color.
- Background color.
- Store logo.
- Wallet logo.
- Wallet hero image.

These are validated by `StoreBrandingRules`.

After saving, the store syncs to the default loyalty program.

### Step 3: Customer form

Route:

- `GET /merchant/onboarding/wizard/customer-form`
- `POST /merchant/onboarding/wizard/customer-form`

Collects customer join form choices:

- Email always required.
- First name optional/enabled/required.
- Last name optional/enabled/required.
- Phone optional/enabled/required.
- Birthday optional/enabled/required.

After saving, the store syncs to the default loyalty program.

### Step 4: Card ready

Route:

- `GET /merchant/onboarding/wizard/card-ready`
- `POST /merchant/onboarding/wizard/card-ready`
- `POST /merchant/onboarding/wizard/complete`

Shows:

- Join URL.
- QR code.
- Launch/share guidance.

Completing onboarding clears `onboarding_step`, sets `onboarding_completed_at`, and redirects to the store QR page.

---

## 7. Customer End-to-End Flow

Controller: `app/Http/Controllers/JoinController.php`

### 7.1 Entry points

Customer routes:

- `/j/{code}` short join redirect.
- `/join/{slug}?t={token}` join landing page.
- `/join/{slug}/new?t={token}` new customer join form.
- `/join/{slug}/existing?t={token}` existing card lookup.
- `/c/{public_token}` customer card.

The app resolves joins by loyalty program first. Legacy store join URLs still fall back to the store default program.

### 7.2 Join landing

The landing page presents the store, loyalty-card name, reward rule, and no-app-required message before offering:

- A primary `Join loyalty card` action.
- A secondary `Find my card` action for returning customers.

If the store or loyalty card is archived, the app shows an archived/invalid state instead of allowing a join.

### 7.3 New customer join

On submit:

1. The app validates fields based on the loyalty program's registration form config.
2. The app finds an existing customer by email or phone where available.
3. If no customer exists, it creates one.
4. If the customer already has a loyalty account for this program, it redirects to the existing card.
5. If the program is at the plan customer limit, it shows the limit reached page.
6. Otherwise it creates a `LoyaltyAccount`.
7. It sends or queues the welcome email with verification token when an email exists.
8. It redirects to the customer card page.

### 7.4 Existing card lookup

Existing lookup is program-scoped.

- If phone lookup is disabled, lookup uses email only.
- If phone lookup is enabled, lookup accepts email or phone.
- If found, the customer is redirected to their card.
- If not found, the customer can try again or create a new card.

### 7.5 Customer card page

Route: `/c/{public_token}`
Controller: `app/Http/Controllers/CardController.php`
View: `resources/views/card/show.blade.php`

The card page shows:

- Store/program branding.
- Customer name.
- Stamp progress in `N of N stamps` language.
- The number of stamps remaining or a clear reward-ready state.
- QR code for stamping or redemption.
- Scan guidance and the manual code immediately below the QR.
- Apple Wallet button.
- Google Wallet button.
- Card-active verification guidance and resend actions when needed.
- A short, customer-friendly recent activity list.

The web card can also expose:

- `/api/card/{public_token}` JSON state.
- `/api/card/{public_token}/transactions` recent transactions.
- `/c/{public_token}/manifest.webmanifest` PWA manifest.

---

## 8. Email Verification

Controllers:

- `app/Http/Controllers/CustomerEmailVerificationController.php`
- `app/Http/Controllers/VerificationController.php`

Verification is tied to the `LoyaltyAccount`, not just the global customer record.

The app stores:

- `email_verification_token_hash`
- `email_verification_expires_at`
- `email_verification_sent_at`
- `verified_at`

Current token lifetime is 1 day.

Verification is required before redemption when the program setting `require_verification_for_redemption` is enabled and the customer has an email address.

Customers without an email are treated as not requiring email verification.

---

## 9. Wallet Pass Creation and Customisation

Current wallet customisation is standardised. There is one wallet card layout and merchants customise the content/assets that feed that layout.

### 9.1 Merchant-customisable wallet inputs

Configured during onboarding, store creation, store edit, or card edit:

- Brand color: hex color used as accent/branding.
- Background color: hex color used for card/page/wallet background.
- Store logo: used on customer web card and as a fallback image.
- Wallet logo: used as Apple pass logo and Google program logo.
- Wallet hero image: used as Apple strip image and Google hero/image module source.
- Reward target: number of stamps needed.
- Reward title: reward earned by the customer.
- Card name/program name.
- Redemption verification setting.
- Join form fields.

Recommended image guidance in the UI:

- Wallet logo: PNG/JPG/WebP, max 2 MB, recommended around 160x50 px.
- Wallet hero: PNG/JPG/WebP, max 2 MB, recommended around 640x180 px or 640x200 px.
- Store logo: PNG/JPG/WebP, max 2 MB.

### 9.2 Important customisation rule

For the active customer-facing loyalty card, use the **Cards** screen to edit card-specific reward rules, join form fields, colors, and wallet images.

The **Store Edit** screen edits store-level identity and fallback/legacy branding, and links to the default loyalty card editor.

During onboarding, store fields are synced into the default loyalty card automatically.

### 9.3 Apple Wallet pass generation

Download route:

- `GET /wallet/apple/{public_token}/download`

Service:

- `app/Services/Wallet/AppleWalletPassService.php`

Apple web service routes:

- `POST /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`
- `DELETE /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`
- `GET /wallet/v1/passes/{passTypeIdentifier}/{serialNumber}`
- `GET /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}`
- `POST /wallet/v1/log`

Apple pass structure:

- `formatVersion`: 1.
- `passTypeIdentifier`: from passgenerator config.
- `teamIdentifier`: from passgenerator config.
- `organizationName`: from passgenerator config.
- `serialNumber`: `kawhe-{loyalty_account_id}`.
- `logoText`: store name.
- `webServiceURL`: app URL plus `/wallet`.
- `authenticationToken`: loyalty account wallet auth token.
- `storeCard`: primary, secondary, auxiliary, and back fields.
- `barcode`: QR code with dynamic stamp/redeem token.

Apple front fields:

- Primary field: stamp indicator circles.
- Secondary field: customer name.
- Auxiliary field: status such as `4/9 stamps` or `Ready`.

Apple back fields:

- Program.
- Progress.
- Status.
- Manual code.
- Reward rule.
- Verification requirement.
- How to use.
- Store name.

Apple assets are generated from the merchant's source uploads using platform-specific, immutable derivatives:

- `logo.png`, `logo@2x.png`, and `logo@3x.png`: transparent rectangular contain-fit logos. Merchant artwork is never circularly masked or stretched.
- `strip.png`, `strip@2x.png`, and `strip@3x.png`: centre-cropped cover derivatives of the wallet hero image.
- `icon.png`: default/fallback wallet icon.
- `background.png`: default/fallback background asset.

Apple barcode behavior:

- If no reward is available, barcode message is `LA:{public_token}` for stamping.
- If reward is available and a redeem token exists, barcode message is `LR:{redeem_token}` for redemption.
- Alt text shows `Manual code: {manual_entry_code}`.

Apple update behavior:

- When a customer adds a pass, Apple registers the device using the Apple Wallet web service.
- Registrations are stored in `AppleWalletRegistration`.
- On stamp/redeem, `UpdateWalletPassJob` queues wallet sync.
- Apple push notifications are sent through the wallet sync service.
- Wallet asks `/wallet/v1/passes/...` for the updated pass.
- The app returns `304 Not Modified` only when neither account state nor programme branding changed since Apple's timestamp.

### 9.4 Google Wallet pass generation

Save route:

- `GET /wallet/google/{public_token}/save`

Service:

- `app/Services/Wallet/GoogleWalletPassService.php`
- `app/Services/Wallet/GoogleWalletStampStripRenderer.php`

Google Wallet creates/updates:

- A loyalty class for the loyalty program.
- A loyalty object for the individual customer card.
- A signed JWT save link that redirects the customer to Google Wallet.

Google class content:

- Issuer name.
- Store/program name.
- Programme logo from a 660x660 circular-safe Google derivative.
- Hero/image module from a 1032x812 Google derivative or fallback logo.
- Background color.
- Text modules for program and reward rule.

Google object content:

- Account name.
- Account ID.
- Loyalty points labeled `Stamps`.
- Secondary points labeled `Rewards`.
- Barcode with `LA:{public_token}` or `LR:{redeem_token}`.
- Alternate text with manual code.
- Status text modules.
- Generated image modules, including the stamp-strip renderer.

Google stamp-strip rendering:

- File path: `wallet/google/stamp-strips/...` on the assets disk.
- Renderer version: `v5` in the current app state.
- Uses program background color, brand color, foreground contrast color, reward target, current stamp count, and wallet hero image.
- Draws filled and empty stamp circles over the hero/background.
- Generates a hash-based filename so updated progress/branding creates a new image path.

Google update behavior:

- On stamp/redeem, `UpdateWalletPassJob` queues sync.
- The sync service updates the Google Wallet object.
- If the class already exists, the service patches changed branding fields when needed.
- If image patching fails, base fields can still update.

### 9.5 Wallet sync triggers

Wallet sync can be triggered by:

- Customer adding a wallet pass.
- Stamp action.
- Redeem action.
- Merchant customer detail `sync wallet` action.
- Store QR/edit `queue wallet refresh for all cards` action.
- Support recovery flows.

Wallet sync is designed not to block the scanner response. The scanner updates the database first, then queues wallet jobs.

### 9.6 Wallet artwork versioning and reliability

`LoyaltyProgram` stores an explicit wallet design version, deterministic design hash, generated-artwork manifest, generated timestamp, and branding-update timestamp.

The artwork renderer:

- Keeps original `pass_logo_path` and `pass_hero_image_path` uploads unchanged.
- Generates separate Apple and Google PNG outputs.
- Uses versioned/hash-based filenames for provider cache-busting.
- Retains the immediately previous manifest for rollback safety.
- Queues programme refresh work after visible branding changes.

Wallet account jobs use bounded retries, exponential-style backoff, unique account keys, and overlap protection. Apple and Google failures are isolated so one provider is still attempted when the other fails. Merchant-safe results are stored in support audit logs.

Onboarding and card edit show separate Apple and Google previews. Card edit also shows provider health and a queued retry action. Apple and Google still control final device typography and spacing.

---

## 10. QR Codes and Posters

The app generates QR codes for:

- Store default join link.
- Individual loyalty program/card join link.
- Customer web card stamping/redeeming.
- Wallet pass barcode.

Merchant QR pages:

- `resources/views/stores/qr.blade.php`
- `resources/views/programs/qr.blade.php`

Poster downloads:

- Store poster PDF/image.
- Program/card poster PDF/image.

QR sharing behavior:

- If a store is archived, QR sharing is blocked and the merchant is redirected to restore/edit.
- If a loyalty card is archived, card QR sharing is blocked until restored.

---

## 11. Billing and Self-Serve SaaS Limits

Plan enforcement is handled by `UsageService`.

Current self-serve limits:

- Free: 1 store, 1 loyalty card per store, 100 customers per card.
- Pro: 3 stores, 5 loyalty cards per store, unlimited customers per card.
- Business: reserved, not currently sold.

Limits are checked when:

- Creating a store.
- Creating a loyalty card.
- Accepting a new customer join.
- Exporting customers.

Limits do not delete existing stores, cards, or customers. They block new growth only.

Billing recovery behavior:

- Checkout handles missing Stripe config with user-safe errors.
- Checkout retries once if a stale Stripe customer ID is detected.
- Billing portal clears stale Stripe IDs when a Stripe account change makes the old customer unavailable.
- Success page tries Cashier sync first and then direct Stripe subscription sync.
- Manual sync can sync by checkout session or Stripe customer.
- Billing failures are written to `SupportAuditLog` with event type `billing_issue`.

---

## 12. Merchant Mobile App Integration

API routes: `routes/api.php`

Auth endpoints:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`

Store endpoint:

- `GET /api/v1/stores`

Scanner endpoints:

- `POST /api/v1/scanner/preview`
- `POST /api/v1/stamp`
- `POST /api/v1/redeem/info`
- `POST /api/v1/redeem`

The mobile app uses Sanctum tokens.

Important mobile behavior:

- Login returns the API token and user details.
- Store list returns merchant-owned stores and branding fields.
- Scanner/stamp/redeem endpoints reuse the same scanner controller logic as the web scanner.
- Verified middleware applies to scanner actions in the API route group.

---

## 13. Admin Panel

Routes:

- `GET /admin/dashboard`
- `GET /admin/support`

Middleware:

- Authenticated user.
- Super admin middleware.

Admin dashboard controller:

- `app/Http/Controllers/AdminDashboardController.php`

Admin dashboard shows:

- Total users.
- Total stores.
- Today's stamp count.
- Support events in the last 7 days.
- Recent stores.
- Recent stamp activity.
- Recent support activity.
- Activity trends for joins, stamps, redeems.
- Store growth trend.
- Merchant diagnostics for repeated billing or wallet issues.

Admin support logs show:

- All support audit logs.
- Filters by event type, status, store, search term, issues only.
- Summary cards for failed, wallet, and billing issue counts.

---

## 14. Important Files by Area

### Routing

- `routes/web.php`
- `routes/api.php`
- `routes/auth.php`

### Merchant control panel

- `resources/views/layouts/merchant.blade.php`
- `resources/views/components/merchant-layout.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/stores/index.blade.php`
- `resources/views/stores/edit.blade.php`
- `resources/views/stores/qr.blade.php`
- `resources/views/programs/index.blade.php`
- `resources/views/programs/partials/form.blade.php`
- `resources/views/merchant/customers/index.blade.php`
- `resources/views/merchant/customers/show.blade.php`
- `resources/views/scanner/index.blade.php`
- `resources/views/billing/index.blade.php`

### Controllers

- `app/Http/Controllers/MerchantOnboardingWizardController.php`
- `app/Http/Controllers/StoreController.php`
- `app/Http/Controllers/LoyaltyProgramController.php`
- `app/Http/Controllers/JoinController.php`
- `app/Http/Controllers/CardController.php`
- `app/Http/Controllers/ScannerController.php`
- `app/Http/Controllers/WalletController.php`
- `app/Http/Controllers/BillingController.php`
- `app/Http/Controllers/MerchantCustomersController.php`
- `app/Http/Controllers/SupportLogController.php`
- `app/Http/Controllers/AdminDashboardController.php`

### Domain models

- `app/Models/User.php`
- `app/Models/Store.php`
- `app/Models/LoyaltyProgram.php`
- `app/Models/Customer.php`
- `app/Models/LoyaltyAccount.php`
- `app/Models/StampEvent.php`
- `app/Models/PointsTransaction.php`
- `app/Models/AppleWalletRegistration.php`
- `app/Models/SupportAuditLog.php`

### Services and support classes

- `app/Services/Loyalty/StampLoyaltyService.php`
- `app/Services/Analytics/MerchantAnalyticsService.php`
- `app/Services/Billing/UsageService.php`
- `app/Services/Support/SupportAuditService.php`
- `app/Services/Support/MerchantRecoveryService.php`
- `app/Support/StoreBrandingRules.php`
- `app/Support/StoreAssets.php`
- `app/Support/RegistrationFormConfig.php`

### Wallet

- `app/Jobs/UpdateWalletPassJob.php`
- `app/Services/Wallet/WalletSyncService.php`
- `app/Services/Wallet/AppleWalletPassService.php`
- `app/Services/Wallet/Apple/ApplePassService.php`
- `app/Services/Wallet/Apple/ApplePushService.php`
- `app/Services/Wallet/Apple/AppleWalletSerial.php`
- `app/Http/Controllers/Wallet/AppleWalletController.php`
- `app/Services/Wallet/GoogleWalletPassService.php`
- `app/Services/Wallet/GoogleWalletStampStripRenderer.php`
- `resource_path('wallet/apple/default')` for default Apple assets.

### Billing and Stripe

- `app/Http/Controllers/BillingController.php`
- `app/Services/Billing/UsageService.php`
- `config/billing.php`
- `config/cashier.php`
- `routes/web.php` Stripe webhook route.

---

## 15. Data and Safety Notes

### Tenant boundaries

Most merchant queries must be scoped by the authenticated user's stores.

Use:

- `Store::queryForUser($user)` for store access.
- Ownership checks in scanner/customer controllers.
- Super admin only for platform-level routes.

### Destructive flows

Stores and loyalty programs use archive/restore rather than immediate hard delete.

Archive impact:

- New joins pause.
- QR sharing pauses.
- Existing customer records remain.
- Existing wallet passes may remain installed.
- History remains available for support.

### Scanner safety

Scanner logic includes:

- Merchant ownership checks.
- Account row locking.
- Idempotency keys.
- Duplicate scan window.
- Configurable cooldown.
- Audit records.
- Wallet sync after database commit.

### Wallet safety

Wallet pass auth uses a separate `wallet_auth_token`. Public card tokens are not used as Apple web-service auth tokens.

### Billing safety

Billing limits block new resource creation and new joins. They do not delete existing data.

### Secrets

Never commit:

- `.env`
- Stripe keys.
- Stripe webhook secrets.
- Apple Wallet certificates/keys.
- Google Wallet service account JSON.
- SMTP/SendGrid credentials.

---

## 16. Operational Notes

### Local development

Run Laravel and Vite separately unless using the project's combined dev command:

```bash
php artisan serve --port=8000
npm run dev
```

If realtime stamp updates are being tested, run Reverb as well:

```bash
php artisan reverb:start
```

### Testing

Focused tests used often for wallet/store/billing safety:

```bash
php artisan test tests/Feature/GoogleWalletStampStripRendererTest.php tests/Feature/SelfServeSaasBaselineTest.php tests/Feature/StoreTest.php tests/Feature/BillingAndWalletEntrypointsTest.php
```

Full suite:

```bash
php artisan test
```

Build check:

```bash
npm run build
```

### Deployment

Testing deploys currently use:

```bash
ssh root@134.199.159.188 'cd /var/www/kawhe-testing && ./ops/deploy-testing.sh <commit-sha>'
```

Production deploys should follow the production ops docs and only deploy reviewed commits.

Important deployment steps:

- Pull/checkout target commit.
- Install Composer dependencies if needed.
- Build Vite assets.
- Run migrations.
- Clear/cache config, routes, events, and views.
- Ensure queues and Reverb are running.
- Run health checks.

### Testing health warning

The testing server may report a health warning because `APP_ENV=testing` and `APP_DEBUG=true`. That is not the same as a Laravel boot failure.

---

## 17. Current Launch Readiness Checklist

Before considering the app ready for broad self-serve SaaS promotion, verify:

- Merchant registration works end to end.
- Onboarding wizard creates a default store and default loyalty card.
- Store/logo/wallet hero uploads work in production storage.
- Join QR opens correctly on mobile.
- New customer join works.
- Existing card lookup works.
- Email verification email is delivered.
- Apple Wallet pass downloads and installs on iPhone.
- Apple Wallet pass updates after stamp/redeem.
- Google Wallet save link works on Android.
- Google Wallet object updates after stamp/redeem.
- Scanner web flow works.
- Merchant mobile scanner flow works.
- Stripe checkout uses live keys in production.
- Stripe webhook is configured and receiving events.
- Billing sync works after checkout.
- Support logs capture wallet, email, manual support, and billing failures.
- Admin dashboard/support logs are accessible only to super admins.
- Queue workers are running.
- Reverb is running if realtime updates are expected.
- Backups and server monitoring are in place.

---

## 18. What To Avoid Reintroducing Without A Plan

Do not casually reintroduce:

- Multiple wallet visual card types.
- Abstract wallet pattern rendering.
- Custom stamp icon uploads.
- Apple Wallet asset experiments without real device testing.
- Hard deletes for stores/cards.
- Scanner contract changes that would break the mobile app.
- Join URL/token changes that would break printed QR posters.

If wallet design variants are revisited later, they should be implemented as a separate planned project with:

- Data model migration plan.
- Backward compatibility plan.
- Apple Wallet on-device test matrix.
- Google Wallet Android test matrix.
- Merchant preview parity requirements.
- Rollback plan.
