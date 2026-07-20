# Kawhe Loyalty — Developer Handover

**Last updated:** July 20, 2026
**Audience:** Engineers joining the project with no prior context.

This document describes how the product works today: domain model, user flows, key files, validation rules, and where to change things safely.

For the full current end-to-end product documentation, read [`FULL_SYSTEM_DOCUMENTATION.md`](FULL_SYSTEM_DOCUMENTATION.md). For deeper operational guides (wallet setup, billing, production ops), see the linked docs at the end.

---

## 1. What Kawhe Is

Kawhe is a SaaS loyalty platform for merchants (cafés, retail, etc.) and their customers.

| Actor | Experience |
|-------|------------|
| **Merchant** | Registers, completes a setup wizard, configures loyalty cards, shares QR/join links, scans customer cards to stamp/redeem, manages billing |
| **Customer** | Joins via link/QR, gets a web loyalty card, optionally saves to Apple/Google Wallet, tracks stamps and redeems rewards |
| **Merchant mobile app** | Flutter client using `/api/v1` for auth, store list, scanner preview/stamp/redeem (same backend logic as web scanner) |

**Single Laravel 12 backend** serves merchant web UI, customer join/card pages, wallet web services, and mobile API.

---

## 2. Tech Stack

| Layer | Choice |
|-------|--------|
| Backend | PHP 8.2+, Laravel 12 |
| Auth | Laravel Breeze + Sanctum (API) |
| Billing | Laravel Cashier (Stripe) |
| Frontend | Blade, Tailwind CSS, Alpine.js, Vite |
| Real-time | Laravel Reverb + Echo |
| Tests | Pest |
| Wallets | Apple PassKit + Google Wallet API |
| Storage | Local (dev); DigitalOcean Spaces (production assets) |

**Common commands:** see root `AGENTS.md` and `docs/RUN_PROJECT.md`.

---

## 3. Domain Model (Read This First)

### Mental model

```
User (merchant)
  └── Store (physical location / merchant account container)
        └── LoyaltyProgram ("loyalty card" — what customers actually join)
              └── LoyaltyAccount (one customer’s membership on that card)
                    └── Customer (person; can have many accounts across cards)
```

**Important:** Customer join URLs, QR codes, branding, and registration forms are **program-centric**. The `Store` still holds legacy/compatibility fields and is updated during onboarding, then synced to the default program.

### `Store`

- Merchant-owned (`user_id`)
- Holds address, onboarding state, and (during wizard) branding/reward/form config
- Auto-generates `slug`, `join_token`, `join_short_code` on create
- **`syncDefaultProgramFromStore()`** — pushes reward, branding, wallet assets, and `registration_form_config` to the default `LoyaltyProgram` after wizard steps and registration

Onboarding fields on `stores`:
- `onboarding_step` — `store_basics` → `card_design` → `customer_form` → `card_ready` → `null` when complete
- `onboarding_completed_at`

### `LoyaltyProgram`

- A merchant-facing **“loyalty card”** (UI label: “Cards” in nav)
- Each program has its own `slug`, `join_token`, `join_short_code`, branding, reward rules, and join form config
- **`is_default`** — one default program per store; created at merchant registration
- Soft-deletable (archived cards keep history; new joins paused)
- Join URL: `$program->join_url` (uses program slug + token)

### `LoyaltyAccount`

- Links **one customer** to **one loyalty program** (unique on `loyalty_program_id` + `customer_id`)
- Holds stamp count, reward balance, tokens, verification state
- **`public_token`** — customer card URL `/c/{public_token}`
- **`wallet_auth_token`** — Apple Wallet web service auth (separate from public token)
- Apple Wallet serial: **`kawhe-{loyalty_account_id}`** (legacy `kawhe-{store_id}-{customer_id}` still resolves)

### `Customer`

- Global person record (email, phone, name, birthday)
- Matched on join by email and/or phone when enabled

### Support classes (shared logic)

| Class | Purpose |
|-------|---------|
| `App\Support\RegistrationFormConfig` | Parse/normalize join form field toggles from requests; `phoneLookupEnabled()` |
| `App\Support\StoreBrandingRules` | Required branding + wallet asset validation (onboarding + add-store) |
| `App\Support\StoreAssets` | Upload/delete/url for logos and pass images |
| `App\Services\Wallet\Apple\AppleWalletSerial` | Serial format + legacy resolution |

---

## 4. Merchant Flows

### 4.1 Registration → onboarding wizard

1. **`POST /register`** — `RegisteredUserController`
   - Creates user + placeholder store + **default loyalty program**
   - Syncs store → default program
   - Redirects to **`merchant.onboarding.wizard.store-basics`**

2. **Wizard** — `MerchantOnboardingWizardController` (4 steps, dedicated `onboarding-layout`, no sidebar)

| Step | Route | Saves | Next |
|------|-------|-------|------|
| 1 Store basics | `GET/POST …/wizard/store-basics` | name, address, reward_target, reward_title | card-design |
| 2 Card design | `GET/POST …/wizard/card-design` | brand colors, logo, pass_logo, pass_hero_image (**all required**) | customer-form |
| 3 Customer form | `GET/POST …/wizard/customer-form` | `registration_form_config` | card-ready |
| 4 Card ready | `GET …/wizard/card-ready` | preview join URL, QR, poster | complete |
| Complete | `POST …/wizard/complete` | sets `onboarding_completed_at`, clears step | store QR page |

After each step that changes store fields, **`syncDefaultProgramFromStore()`** runs so customer join pages match wizard config.

**Legacy routes (do not use for new work):**
- `GET /merchant/onboarding/store` → redirects to wizard step 1
- `POST /merchant/onboarding/store` → redirects to wizard (no silent store create)
- `GET …/wizard/continue-trial` → redirects to card-ready (removed step 5)

**Middleware:** `EnsureMerchantHasStore` redirects merchants without a completed store to the wizard. Onboarding routes are **outside** this middleware.

### 4.2 Adding another store

- **`StoreController@store`** — same branding requirements as wizard (`StoreBrandingRules`)
- Creates store + default program; redirects to stores index

### 4.3 Managing loyalty cards

- **`LoyaltyProgramController`** — `/merchant/stores/{store}/programs`
- Create/edit/archive cards; each gets its own join link and QR
- Registration form editor uses shared component **`x-registration-form-config-editor`** (presets, field toggles)
- Reward target **locks** after customers have joined (`hasIssuedCards`)
- Free plan: 1 store, 1 card per store, 100 customers per card — see `UsageService` and `config/billing.php`

### 4.4 Registration form configuration

Configured fields: **email** (always on/required), optional **first_name**, **last_name**, **phone**, **birthday** — each can be enabled and optionally required.

Shared UI: `resources/views/components/registration-form-config-editor.blade.php`
Shared parsing: `RegistrationFormConfig::fromRequest()`

Presets in UI: Fastest, Balanced, Marketing-friendly.

---

## 5. Customer Join Flows

Controller: **`JoinController`**

### Entry URLs

| URL | Behavior |
|-----|----------|
| `/j/{code}` | Short code → resolves **program** (or store default program) → full join URL |
| `/join/{slug}?t={token}` | Landing page |
| `/join/{slug}/new?t={token}` | New customer signup form |
| `/join/{slug}/existing?t={token}` | Find existing card |

**Slug + token** resolve to `LoyaltyProgram` first; legacy store slug/token still work via `resolvedDefaultProgram()`.

Invalid slug/token → **`join.invalid`** (branded 404, not generic abort).
Archived store/program → **`join.archived`**.

### New join (`POST join.store`)

- Validates fields from **`program.registration_form_config`**
- Finds/creates `Customer` by email, then phone
- Creates `LoyaltyAccount` scoped to **`loyalty_program_id`**
- Existing account for same program → redirect to card (no duplicate)
- Welcome email + verification token when customer has email
- Session flags: `registered`, `show_wallet_nudge` (wallet CTA on first visit only)

### Existing card lookup (`POST join.lookup`, throttled 10/min)

- **Email-only** by default
- If program has **phone enabled** in form config → email **or** phone (at least one required)
- Program-scoped: only finds accounts on **this** loyalty card
- Success → redirect to `/c/{public_token}` with `show_wallet_nudge`

### Customer card page (`/c/{public_token}`)

- localStorage keys use **`loyalty_program_id`** (legacy store keys cleaned up on load)
- “You’re in!” banner when `session('registered')`
- Wallet nudge when `session('show_wallet_nudge')` — not every visit

---

## 6. Scanner, Stamp, Redeem

Unchanged architecturally — see `StampLoyaltyService`, `ScannerController`, `/api/v1/*`.

Token formats: `LA:{public_token}`, `LR:{redeem_token}`, 4-char manual codes, plain tokens.

Store/program verification for redemption uses **`require_verification_for_redemption`** on the program (synced from store for default card).

---

## 7. Wallet Integrations

### Platform artwork

The merchant still uploads one wallet logo and one wallet hero image. `WalletArtworkService` keeps those source paths unchanged and generates separate immutable Apple and Google derivatives recorded in `loyalty_programs.wallet_asset_manifest`.

- Apple uses rectangular transparent logo assets at 1x/2x/3x and strip assets at 1x/2x/3x.
- Google uses a circular-safe 660x660 programme logo and 1032x812 hero image.
- Visible branding changes increment `wallet_design_version`, change derivative URLs, and queue chunked wallet refresh work.
- Apple effective modification time is the later of account activity and `wallet_branding_updated_at`.
- Google wallet-sync audit entries retain separate class and customer-object outcomes without changing their IDs.
- Merchant wallet health treats provider credentials as configured only when the referenced local credential file is readable.
- Account sync jobs retry transient failures and isolate Apple from Google.

Main classes: `App\Services\Wallet\Artwork\WalletArtworkService`, `WalletHealthService`, `RefreshProgramWalletsJob`, and `UpdateWalletPassJob`.

### Apple Wallet serial numbers

- **Current:** `kawhe-{loyalty_account_id}`
- **Legacy (still resolved):** `kawhe-{store_id}-{customer_id}` — prefers default program when ambiguous
- Class: `App\Services\Wallet\Apple\AppleWalletSerial`

### Multi-card implication

One customer can hold **multiple loyalty accounts** (different programs). Each gets its own Apple pass serial and localStorage scope.

---

## 8. Billing & Limits

Plan entitlements live in `config/billing.php` and are enforced by `UsageService`:

| Plan | Stores | Cards / store | Customers / card |
|------|--------|---------------|------------------|
| Free | 1 | 1 | 100 |
| Pro | 3 | 5 | Unlimited |
| Business | Coming soon | Unlimited | Unlimited |

Soft gates only: existing data keeps working; limits block **new** stores, cards, or joins.

- `canCreateStore()` — additional stores
- `canCreateProgram($user, $store)` — loyalty cards on a store
- `canAcceptNewCustomer($program)` — customer join (`join/limit-reached`)

Full detail: `docs/BILLING_IMPLEMENTATION_SUMMARY.md`

---

## 9. Frontend Conventions

| Area | Pattern |
|------|---------|
| Merchant dashboard | `x-merchant-layout` + sidebar nav (“Cards”, Stores, Scanner, …) |
| Onboarding wizard | `x-onboarding-layout` — no sidebar; logo, help, logout |
| Join pages | Standalone HTML; program branding (`brand_color`, `background_color`, logo) |
| Forms | `<x-input-error>`, submit loading via `data-loading-text` + small inline script |
| Programs empty state | `programs/index.blade.php` when no active/archived cards |

---

## 10. Tests You Should Run

After touching onboarding, join, or programs:

```bash
php artisan test --filter='MerchantOnboardingIntegrationTest|RefactoredJoinFlowTest|JoinTest|LoyaltyProgramTest|SelfServeSaasBaselineTest|AppleWalletSerialTest|StoreTest'
```

Key scenarios covered:
- Full wizard completion with required branding assets
- Legacy onboarding POST redirect
- Program-scoped email lookup + phone lookup when enabled
- Invalid join branded 404
- Multi-program join creates account under correct program

---

## 11. Where To Change What

| Task | Start here |
|------|------------|
| Wizard step content/validation | `MerchantOnboardingWizardController`, wizard views under `resources/views/merchant/onboarding/wizard/` |
| Join form fields (merchant config) | `RegistrationFormConfig`, `registration-form-config-editor` component |
| Join customer UX | `JoinController`, `resources/views/join/*` |
| Branding required rules | `StoreBrandingRules`, `StoreController`, wizard `card-design` |
| Store → program sync | `Store::syncDefaultProgramFromStore()` |
| New loyalty card CRUD | `LoyaltyProgramController`, `programs/partials/form.blade.php` |
| Apple serial / multi-card | `AppleWalletSerial`, `card/show.blade.php` localStorage |
| Wallet ops / APNs debug | `docs/WALLET_COMMANDS.md`, `docs/APPLE_WALLET_TESTING_GUIDE.md` |
| Billing limits | `UsageService`, `BillingController` |

**Do not rename** public routes, API contracts, queue names, or event names without explicit approval.

---

## 12. Migrations to Know

| Migration | Effect |
|-----------|--------|
| `2026_06_25_000001_allow_multiple_loyalty_accounts_per_customer` | Drops unique `(store_id, customer_id)`; adds unique `(loyalty_program_id, customer_id)` |

Run `php artisan migrate` on deploy.

---

## 13. Documentation Map

| Doc | Use when |
|-----|----------|
| **`docs/DEVELOPER_HANDOVER.md`** (this file) | Onboarding a new engineer |
| **`docs/FULL_SYSTEM_DOCUMENTATION.md`** | End-to-end system reference |
| **`docs/MERCHANT_ONBOARDING_AND_CARD_DESIGN_FLOW.md`** | Wizard/field-level onboarding detail |
| **`docs/TECHNICAL_DOCUMENTATION.md`** | Architecture, API, security patterns |
| **`AGENTS.md`** | AI agent / delivery guardrails |
| **`docs/RUN_PROJECT.md`** | Local setup |
| **`docs/SELF_SERVE_LAUNCH_CHECKLIST.md`** | Pre-launch QA |
| **`docs/TEST_AS_CUSTOMER.md`** | Wallet testing on device |
| **`docs/WALLET_COMMANDS.md`** | APNs commands and wallet log debugging |
| **`docs/APPLE_WALLET_TESTING_GUIDE.md`** | Full Apple Wallet test checklist |
| Other wallet/billing/ops | `docs/APPLE_WALLET_SETUP.md`, `docs/BILLING_SETUP.md`, `docs/PRODUCTION_OPS.md`, etc. |

---

## 14. Recent Platform Changes (June 2026)

Summarized for handover — full detail in `docs/CHANGES_SUMMARY.md`.

1. **Store ↔ default program sync** — wizard writes store; join reads program; sync keeps them aligned.
2. **Required branding** — logo, wallet logo, wallet hero, colors required on onboarding and add-store.
3. **Customer join UX** — CTA order, loading states, branded invalid links, welcome banner, wallet nudge once.
4. **Onboarding UX** — 4-step wizard, setup-mode layout, card-ready iframe preview.
5. **Shared registration form editor** — wizard + program edit parity.
6. **Phone lookup** — when phone field enabled on card, existing-customer recovery accepts phone.
7. **Apple Wallet serial** — per loyalty account; supports legacy serials.
8. **Multi-card** — one customer can join multiple programs; accounts unique per program.
