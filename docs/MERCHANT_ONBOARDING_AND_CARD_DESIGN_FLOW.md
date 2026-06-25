# Merchant Onboarding, Store Creation, and Card Design

**Last updated:** June 2026

This document describes the **current** merchant setup flow: registration, the 4-step onboarding wizard, additional store creation, and loyalty card (program) management. Use it when changing setup steps, validation, or branding requirements.

---

## 1. Entry points

| Entry | Route | What happens |
|-------|--------|--------------|
| Public start | `GET /start` | Marketing page → Register or Login |
| Register | `GET/POST /register` | Creates user + store shell + **default loyalty program**; syncs store → program; redirects to **wizard step 1** |
| Login | `POST /login` | Redirects to dashboard or intended URL |
| Dashboard (no completed store) | `GET /merchant/dashboard` | Redirect to wizard (via middleware or route logic) |
| Legacy onboarding GET | `GET /merchant/onboarding/store` | Redirect → `merchant.onboarding.wizard.store-basics` |
| Legacy onboarding POST | `POST /merchant/onboarding/store` | Redirect → wizard with info message (**does not create a store**) |

**Middleware:** `EnsureMerchantHasStore` blocks merchant routes until onboarding is complete. Onboarding wizard routes are **exempt**.

---

## 2. Onboarding wizard (first store)

**Controller:** `MerchantOnboardingWizardController`  
**Layout:** `x-onboarding-layout` (no sidebar — focused setup mode)  
**State:** `stores.onboarding_step`, `stores.onboarding_completed_at`

After each step that updates store fields, **`Store::syncDefaultProgramFromStore()`** pushes config to the default `LoyaltyProgram` so customer join pages stay in sync.

### Step 1 — Store basics

| | |
|--|--|
| **Routes** | `GET/POST /merchant/onboarding/wizard/store-basics` |
| **View** | `merchant/onboarding/wizard/store-basics.blade.php` |

**Fields:**

| Field | Name | Validation |
|-------|------|------------|
| Store name | `name` | required, max 255 |
| Address | `address` | nullable |
| Stamps for reward | `reward_target` | required, integer, min 1 |
| Reward title | `reward_title` | required, max 255 |

Store details may be collapsed if pre-filled from registration (`store_name` on register form).

**On submit:** create or update store; set `onboarding_step = card_design`; sync program; redirect to card design.

---

### Step 2 — Card design

| | |
|--|--|
| **Routes** | `GET/POST /merchant/onboarding/wizard/card-design` |
| **View** | `merchant/onboarding/wizard/card-design.blade.php` |
| **Validation** | `StoreBrandingRules::validationRules($store)` |

**Fields (all required on first pass):**

| Field | Name | Notes |
|-------|------|-------|
| Brand color | `brand_color` | hex `#RRGGBB` |
| Background color | `background_color` | hex |
| Store logo | `logo` | image, max 2MB |
| Wallet logo | `pass_logo` | Apple/Google pass logo (~160×50) |
| Wallet hero | `pass_hero_image` | pass banner (~640×180) |

On wizard **revisit**, file uploads are required only if not already saved on the store.

UI: live color sync (Alpine), dropzone-style uploads, preview panel. Continue disabled until all assets present (client-side guard + server validation).

**On submit:** save assets via `StoreAssets`; set `onboarding_step = customer_form`; sync program.

---

### Step 3 — Customer form

| | |
|--|--|
| **Routes** | `GET/POST /merchant/onboarding/wizard/customer-form` |
| **View** | `merchant/onboarding/wizard/customer-form.blade.php` |
| **Component** | `x-registration-form-config-editor` |
| **Parsing** | `RegistrationFormConfig::fromRequest()` |

**Fields collected from customers at join time:**

- **Email** — always enabled/required (not toggleable)
- **First name, Last name, Phone, Birthday** — each: `_enabled` + `_required` checkboxes (hidden `0` values when unchecked)

UI includes quick presets (Fastest / Balanced / Marketing-friendly), café recommendation callout, friction indicator, and signup preview panel.

**On submit:** save `registration_form_config`; set `onboarding_step = card_ready`; sync program.

---

### Step 4 — Card ready

| | |
|--|--|
| **Routes** | `GET /merchant/onboarding/wizard/card-ready`, `POST /merchant/onboarding/wizard/complete` |
| **View** | `merchant/onboarding/wizard/card-ready.blade.php` |

Shows:
- Join URL from **`$program->join_url`** (default program)
- QR code, copy link, download poster PDF
- Phone-frame iframe preview of join page
- Primary CTA completes onboarding

**On complete:** `onboarding_step = null`, `onboarding_completed_at = now()` → redirect to **`merchant.stores.qr`** for the store.

---

## 3. Create additional store

**Routes:** `GET/POST /merchant/stores` (create)  
**View:** `resources/views/stores/create.blade.php`  
**Validation:** same **`StoreBrandingRules`** as wizard (all branding assets required on create)

Creates store + default program. Redirects to stores index.

Requires active subscription / plan limits per `UsageService`.

---

## 4. Loyalty cards (programs)

Merchants manage cards at **`/merchant/stores/{store}/programs`**.

| Action | Route |
|--------|-------|
| List | `GET …/programs` |
| Create | `GET/POST …/programs/create` |
| Edit | `GET/PUT …/programs/{program}/edit` |
| Archive | `DELETE …/programs/{program}` |
| Restore | `POST …/programs/{program}/restore` |
| QR / poster | `GET …/programs/{program}/qr`, `…/qr/pdf` |

**Create/edit form** (`programs/partials/form.blade.php`):
- Reward, branding, wallet assets, verification toggle
- **Join form fields** via shared `x-registration-form-config-editor` (presets enabled)
- Reward target locked on edit when customers already joined

**Empty state:** index shows CTA when no active or archived cards.

Each program has its own `slug`, `join_token`, `join_short_code`, and join URL.

---

## 5. Edit store (legacy store-level settings)

**Routes:** `GET/PUT /merchant/stores/{store}/edit`  
Still used for store name, address, and legacy fields. Default card branding for customer join is primarily on the **default loyalty program**; wizard sync keeps store and default program aligned during onboarding.

---

## 6. Where branding and form config are consumed

| Surface | Source |
|---------|--------|
| Join landing / new / existing | `$program` branding + `$program->registration_form_config` |
| Customer card page | Account’s program + store |
| QR poster (store) | Store (legacy) |
| QR poster (program) | Program |
| Apple / Google Wallet | Program/store branding on account’s store and program |

When adding a new branding or form option, update:
1. Migration + model fillable
2. Wizard and/or program form + controller validation
3. `syncDefaultProgramFromStore()` if store-level during onboarding
4. Join views and wallet pass services

---

## 7. Database columns (quick reference)

### `stores` (onboarding-relevant)

- `onboarding_step`, `onboarding_completed_at`
- `reward_target`, `reward_title`
- `brand_color`, `background_color`
- `logo_path`, `pass_logo_path`, `pass_hero_image_path`
- `registration_form_config` (JSON)
- `require_verification_for_redemption`

### `loyalty_programs`

Mirrors reward, branding, join tokens, form config per card. See `LoyaltyProgram` model `$fillable`.

### `loyalty_accounts`

- Unique on `(loyalty_program_id, customer_id)` — customer can hold multiple cards across programs

---

## 8. Flow summary

```
Register → Wizard (4 steps) → Complete → Store QR page
                ↓ sync after each step
         Default LoyaltyProgram (customer join reads this)

Additional stores → Create store form (required branding) → Stores index
Additional cards  → Programs create/edit (shared form editor) → Program QR
```

---

## 9. Tests

```bash
php artisan test --filter='MerchantOnboardingIntegrationTest|SelfServeSaasBaselineTest|StoreTest|LoyaltyProgramTest'
```

Covers: full wizard path, required assets, legacy POST redirect, program form config persistence.
