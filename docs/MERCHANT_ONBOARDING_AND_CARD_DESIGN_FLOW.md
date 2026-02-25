# Merchant Onboarding, Store Creation, and Card Design – Full Flow

This document describes **every step** from onboarding a new merchant through creating a store and configuring card design in the current app. Use it when changing steps or adding options so nothing is missed.

---

## 1. Entry points: how a merchant gets into the app

| Entry | Route | What happens |
|-------|--------|---------------|
| **Public start** | `GET /start` | Renders `resources/views/public/start.blade.php`. Marketing page: “Kawhe Loyalty”, “Create your café loyalty QR program in minutes”, bullets (Digital Stamp Cards, Instant Setup, Real-Time Updates). Buttons: **Create Free Account** → `route('register')`, **Log In** → `route('login')`. |
| **Register** | `GET /register`, `POST /register` | `RegisteredUserController`: create user, fire `Registered`, send `MerchantWelcomeEmail` (sync or queue per `config('mail.welcome_sync')`), log in user, **redirect to `merchant.onboarding.store`** (onboarding). |
| **Login** | `POST /login` | `AuthenticatedSessionController`: authenticate, regenerate session, **redirect to `intended('/dashboard')`**. |
| **Dashboard (no store)** | `GET /dashboard` | In `routes/web.php`: if super_admin → `admin.dashboard`; else if `user->stores()->count() > 0` → `merchant.dashboard`; **else** → `view('dashboard')` (same blade as merchant dashboard but no `$usageStats`). Dashboard view has “My Stores”, “Customers”, “Scanner” cards with links to `merchant.stores.index`, `merchant.stores.create`, `merchant.customers.index`, `merchant.scanner`. |
| **Any merchant route with 0 stores** | e.g. `GET /merchant/dashboard` | Middleware `EnsureMerchantHasStore`: if user has no stores → **redirect to `merchant.onboarding.store`**. Exempt routes: any `merchant.stores.*` and `merchant.stores.qr` so they can create/view stores and QR. |

So:

- **New signup** → always goes to **onboarding** (first store).
- **Existing merchant with no store** → either lands on `/dashboard` (generic dashboard) or hits a merchant route and gets redirected to **onboarding**.
- **Merchant with at least one store** → `/dashboard` redirects to `merchant.dashboard` (same dashboard view with usage stats).

---

## 2. Onboarding: “Create Your First Store” (first store only)

- **Routes:**  
  - `GET  /merchant/onboarding/store` → `OnboardingController@createStore`  
  - `POST /merchant/onboarding/store` → `OnboardingController@storeStore`
- **View:** `resources/views/merchant/onboarding/store.blade.php`
- **Layout:** `x-merchant-layout`, header: “Create Your First Store”.

### 2.1 Onboarding page content

- **Welcome block:** “Welcome to Kawhe!”, “Let’s set up your first loyalty program…”
- **Form** `POST` → `route('merchant.onboarding.store.store')`, `enctype="multipart/form-data"`.

### 2.2 Fields on onboarding form (in order)

| Field | Name | Type | Validation (controller) | Default / Notes |
|-------|------|------|--------------------------|-----------------|
| Store Name | `name` | text | required, string, max:255 | — |
| Address | `address` | text | nullable, string, max:255 | Optional |
| Stamps needed for reward | `reward_target` | number | required, integer, min:1 | old: 9 |
| Reward Title | `reward_title` | text | required, string, max:255 | old: “Free coffee” |
| Brand Color | `brand_color` | color + text | nullable, regex: `^#[0-9A-Fa-f]{6}$` | old: #0EA5E9. Helper text: “Used for customer card styling”. Inline script syncs color picker and hex input. |
| Background Color | `background_color` | color + text | nullable, regex: `^#[0-9A-Fa-f]{6}$` | old: #1F2937. Helper text: “Used for customer card page background”. Same sync script. |
| Store Logo | `logo` | file | nullable, image, mimes:png,jpg,jpeg,webp, max:2048 | Optional. Hint: “PNG, JPG, or WebP (max 2MB)”. |

**Not on onboarding:** Pass Logo, Pass Hero Image, Require verification for redemption. Those exist only on **Create Store** (post-onboarding) and **Edit Store**.

### 2.3 Onboarding submit logic (`OnboardingController@storeStore`)

1. Validate as above.
2. If `logo` uploaded → `store('logos', 'public')` → set `$validated['logo_path']`.
3. `unset($validated['logo'])`.
4. `Auth::user()->stores()->create($validated)`.
5. **Redirect:** `route('merchant.stores.qr', $store)` with success: “Welcome! Your first store has been created. Here’s your QR code to share with customers.”

So after onboarding the merchant lands on the **QR code page** for the new store, not the store list.

### 2.4 Store model auto-values (on create)

- `slug`: `Str::slug($name) . '-' . Str::random(6)` if empty.
- `join_token`: `Str::random(32)` if empty.
- `join_short_code`: unique 6-char from `JOIN_SHORT_CODE_ALPHABET` (no I,O,0,1).

---

## 3. Create Store (after onboarding – additional stores)

- **Routes:**  
  - `GET  /merchant/stores/create` → `StoreController@create`  
  - `POST /merchant/stores` → `StoreController@store`
- **View:** `resources/views/stores/create.blade.php`
- **Layout:** `x-merchant-layout`, header: “Create Store”.

### 3.1 Create Store form

- **Action:** `route('merchant.stores.store')`, `enctype="multipart/form-data"`.
- **Fields (same order as in blade):**

| Field | Name | Validation | Default / Notes |
|-------|------|------------|----------------|
| Store Name | `name` | required, string, max:255 | — |
| Address | `address` | nullable, string, max:255 | Optional |
| Stamps needed for reward | `reward_target` | required, integer, min:1 | old: 9 |
| Reward Title | `reward_title` | required, string, max:255 | old: “Free coffee” |
| Brand Color | `brand_color` | nullable, regex: `^#[0-9A-Fa-f]{6}$` | #0EA5E9 |
| Background Color | `background_color` | nullable, regex: `^#[0-9A-Fa-f]{6}$` | #1F2937 |
| Store Logo | `logo` | nullable, image, mimes:png,jpg,jpeg,webp, max:2048 | “Used for customer card page.” |
| Pass Logo (Wallet Passes) | `pass_logo` | nullable, image, mimes:png,jpg,jpeg,webp, max:2048 | “Apple Wallet and Google Wallet. Recommended: 160x50px.” |
| Pass Hero Image (Wallet Passes) | `pass_hero_image` | nullable, image, mimes:png,jpg,jpeg,webp, max:2048 | “Banner. Recommended: 640x180px (Apple) or 640x200px (Google).” |

### 3.2 Create submit logic (`StoreController@store`)

1. Validate all above.
2. Logo → `store('logos', 'public')` → `logo_path`.
3. Pass logo → `store('pass-logos', 'public')` → `pass_logo_path`.
4. Pass hero → `store('pass-heroes', 'public')` → `pass_hero_image_path`.
5. `unset($validated['logo'], $validated['pass_logo'], $validated['pass_hero_image'])`.
6. `Auth::user()->stores()->create($validated)`.
7. **Redirect:** `route('merchant.stores.index')` with success “Store created successfully.”

---

## 4. Edit Store (card design + settings)

- **Routes:**  
  - `GET  /merchant/stores/{store}/edit` → `StoreController@edit`  
  - `PUT  /merchant/stores/{store}` → `StoreController@update`
- **View:** `resources/views/stores/edit.blade.php`
- **Authorization:** `Store::queryForUser(Auth::user())->whereKey($store->id)->firstOrFail()` (owner or super_admin).

### 4.1 Edit form fields (order in blade)

| Field | Name | Validation in `update()` | Notes |
|-------|------|---------------------------|-------|
| Store Name | `name` | required, string, max:255 | — |
| Address | `address` | nullable, string, max:255 | — |
| Stamps needed for reward | `reward_target` | required, integer, min:1 | — |
| Reward Title | `reward_title` | required, string, max:255 | — |
| **Require Email Verification for Redemption** | `require_verification_for_redemption` | **Not in controller** | Checkbox, value="1". Alpine `x-data` for warning when unchecked. **Bug:** not validated or saved in `StoreController@update` (see below). |
| Brand Color | `brand_color` | nullable, regex: `^#[0-9A-Fa-f]{6}$` | Color + hex text, sync script. “Used for customer card styling”. |
| Background Color | `background_color` | nullable, regex: `^#[0-9A-Fa-f]{6}$` | “Used for customer card page background”. |
| Store Logo | `logo` | nullable, image, mimes:png,jpg,jpeg,webp, max:2048 | Shows current logo if `$store->logo_path`. “Used for customer card page.” |
| Pass Logo (Wallet Passes) | `pass_logo` | nullable, image, … | Shows current pass logo if set. 160x50px recommended. |
| Pass Hero Image (Wallet Passes) | `pass_hero_image` | nullable, image, … | Shows current hero if set. 640x180 / 640x200 recommended. |

Then separate form: **Delete Store** → `route('merchant.stores.destroy', $store)`, `@method('DELETE')`, confirm dialog.

### 4.2 Update submit logic (`StoreController@update`)

1. Load store with `queryForUser` + `firstOrFail`.
2. Validate only: `name`, `address`, `reward_target`, `reward_title`, `brand_color`, `background_color`, `logo`, `pass_logo`, `pass_hero_image`. **`require_verification_for_redemption` is not validated or saved.**
3. Logo: if new file, delete old `logo_path` (if exists), store new in `logos`, set `logo_path`.
4. Pass logo: same for `pass_logo_path` in `pass-logos`.
5. Pass hero: same for `pass_hero_image_path` in `pass-heroes`.
6. `unset($validated['logo'], $validated['pass_logo'], $validated['pass_hero_image'])`.
7. For each of `logo_path`, `pass_logo_path`, `pass_hero_image_path`: if not set in `$validated`, unset so existing DB value isn’t overwritten with null.
8. `$store->update($validated)`.
9. Redirect to `merchant.stores.index` with “Store updated successfully.”

**Fix needed:** Add `require_verification_for_redemption` to validation (e.g. `['nullable', 'boolean']` or accept checkbox as `'sometimes','in:0,1'`) and merge into `$validated` (e.g. `$validated['require_verification_for_redemption'] = $request->boolean('require_verification_for_redemption');`) so the Edit form checkbox actually persists.

---

## 5. Store list and QR

- **Stores index:** `GET /merchant/stores` → `StoreController@index` → `stores.index` with `$stores` (queryForUser, latest). Table: Store Name, Address, Reward Target (e.g. “9 stamps for Free coffee”), Actions: Edit, QR Code.
- **QR page:** `GET /merchant/stores/{store}/qr` → `StoreController@qr` → `stores.qr` with `$store`, `$joinUrl` (short URL when `join_short_code` set). Shows QR, “Download PDF (A4 poster)”, copy join link, “Back to Stores”.
- **QR PDF:** `GET /merchant/stores/{store}/qr/pdf` → `StoreController@qrPdf` → PDF from `stores.qr-poster` (store branding used there too).

---

## 6. Database: store columns (card design + behaviour)

From `Store` model and migrations:

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint | PK |
| `user_id` | FK users | Owner |
| `name` | string | Store name |
| `slug` | string unique | Used in join URL when not using short code |
| `address` | string nullable | Optional address |
| `reward_target` | integer (default 9) | Stamps needed for one reward |
| `reward_title` | string (default “Free coffee”) | Label for the reward |
| `require_verification_for_redemption` | boolean (default true) | If true, redeem requires verified email; used by scanner/API. **Only editable on Edit form; not saved by current update().** |
| `join_token` | string unique | Long token for join URL |
| `join_short_code` | string (6 chars) | Short code for `/j/{code}` |
| `brand_color` | string(7) nullable | Hex, e.g. #0EA5E9. Card styling, buttons, accents. |
| `logo_path` | string nullable | Path in `storage/app/public`: “logos/…” |
| `background_color` | string(7) nullable | Hex. Customer card page and join page background. |
| `pass_logo_path` | string nullable | “pass-logos/…” – Apple/Google Wallet pass logo (160x50 recommended). |
| `pass_hero_image_path` | string nullable | “pass-heroes/…” – Wallet pass banner (640x180 / 640x200). |
| `timestamps` | | |

---

## 7. Where card design is used (don’t miss when adding options)

### 7.1 Customer-facing web

- **Join landing:** `resources/views/join/landing.blade.php`  
  - `$bg = $store->background_color ?? '#1F2937'`, `$brand = $store->brand_color ?? '#0EA5E9'`.  
  - Logo: `$store->logo_path` (asset URL).
- **Join form (new customer):** `resources/views/join/show.blade.php`  
  - Same `$bg`, `$brand`; luminance-based `$textOnBg`, `$mutedOnBg` for contrast.  
  - Styles: `.join-page` background, `.join-muted`, `.join-card`, `.join-btn`, `.join-input:focus` use `$bg`/`$brand`.  
  - Logo: `$store->logo_path`.
- **Customer card page:** `resources/views/card/show.blade.php`  
  - `theme-color` and body background: `$account->store->background_color ?? '#1F2937'`.  
  - Card border and logo border: `$account->store->brand_color ?? '#0EA5E9'`.  
  - Logo: `$account->store->logo_path`.

### 7.2 QR poster PDF

- **View:** `resources/views/stores/qr-poster.blade.php`  
  - Uses `$store->background_color`, `$store->brand_color` (with fallbacks #FBF8F4, #5C3D2E, #6A3A1F, #5A2D16) for background, text, and button.  
  - Logo: `$store->logo_path` (data URI in controller).  
  - Also uses Apple/Google Wallet badge assets and promo text from `reward_title`.

### 7.3 Wallet passes (Apple / Google)

- **Apple Wallet:** Services use store’s pass logo and hero (and branding) when generating `.pkpass`.
- **Google Wallet:** `GoogleWalletPassService` uses store branding (including pass logo/hero) for the loyalty/generic pass.

So any new “card design” or “store branding” option should be considered for: join landing, join show, card show, qr-poster, and wallet pass generation.

---

## 8. Summary: flow and where to add/change things

1. **Public start** (`/start`) → Register or Login.
2. **Register** → always **Onboarding** (first store): `merchant/onboarding/store` (one form: name, address, reward_target, reward_title, brand_color, background_color, logo). Submit → create store → redirect to **QR page** for that store.
3. **Login** → `/dashboard`; if no stores → same dashboard view; if has stores → redirect to merchant dashboard. Any merchant route with 0 stores → redirect to onboarding.
4. **Create Store** (further stores): `merchant/stores/create` – same as onboarding **plus** pass_logo, pass_hero_image. Submit → store list.
5. **Edit Store:** `merchant/stores/{store}/edit` – all of the above **plus** require_verification_for_redemption (checkbox; currently **not persisted** in `StoreController@update`), current logo/hero previews, and Delete store form.
6. **Card design** lives in: Store name, reward_target, reward_title, brand_color, background_color, logo_path, pass_logo_path, pass_hero_image_path (and require_verification_for_redemption for behaviour). Used on join landing, join show, card show, QR poster PDF, and Apple/Google Wallet passes.

When changing steps or adding options:

- Update the right form(s): onboarding, create, and/or edit.
- Update `OnboardingController@storeStore` and/or `StoreController@store`/`update` (validation + save).
- If adding a new column, add migration and add to `Store::$fillable`.
- Consider usage in: join landing, join show, card show, qr-poster, and wallet pass services.
- Keep `/api/v1` and Flutter app contract unchanged (store list and scanner use store data from API; no change to stamp/redeem/preview/verify-email behaviour required for design-only changes).
