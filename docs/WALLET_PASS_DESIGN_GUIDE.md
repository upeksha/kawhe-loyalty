# Wallet Pass Design Guide

How to change the look and content of **Apple Wallet** and **Google Wallet** passes.  
Edits are in PHP (labels, text, colors) and image files (logos, strip, icon). No HTML.

---

## Apple Wallet

**File:** `app/Services/Wallet/AppleWalletPassService.php`

### What you can change

| What | Where (approx. lines) | Example |
|------|------------------------|--------|
| **Pass description** (shown in Wallet) | 35–36 | `'description' => 'Kawhe Loyalty Card'` |
| **Logo text** (under logo image) | 37 | `'logoText' => $store->name` |
| **Primary field** (main line on front) | 54–59 | `'label' => 'Stamps'`, `'value' => ...` (circle indicators) |
| **Secondary fields** (e.g. rewards, status) | 61–74 | `'label' => 'Rewards'`, `'value' => ...` |
| **Auxiliary fields** (customer, manual code, scan instruction) | 75–102 | `'label' => 'Customer'`, `'Stamp Code'`, `'Scan to'`, etc. |
| **Back of pass** (manual entry, support, terms) | 103–134 | All `backFields`: labels and `'value' => '...'` |
| **Colors** (from store or fallbacks) | 138–144 | `$backgroundColor`, `$foregroundColor`; fallbacks `#1F2937`, `#FFFFFF` |

### Images (Apple)

- **Default assets:** `resources/wallet/apple/default/`
  - `logo.png` – 160×50 (logo on pass)
  - `strip.png` – 375×98 (strip behind content). **Safe zone:** keep important content above the bottom ~40px so Customer/Rewards row aligns with the card.
  - `icon.png` – 29×29 (notifications / lock screen)
  - `background.png` – 180×220 (optional)
- **Per-store (merchant uploads):** Store model `pass_logo_path`, `pass_hero_image_path` (same strip size and bottom safe zone).

Change design by editing the `'label'` and `'value'` strings in `AppleWalletPassService.php` and/or replacing the PNGs above.

---

## Google Wallet

**File:** `app/Services/Wallet/GoogleWalletPassService.php`

### Loyalty Class (template – shared by all passes for a store)

| What | Where (approx. lines) | Example |
|------|------------------------|--------|
| **Issuer / program name** | 89–90 | `setIssuerName`, `setProgramName($store->name)` |
| **Class text modules** | 111–118 | `'header' => 'Reward Target'`, `'body' => '...'` |
| **Background color** | 145–147 | `setHexBackgroundColor($store->background_color)` |
| **Program logo** | 92–101 | `getPassLogoUri` / `getLogoUri` / `getDefaultLogoUri` |
| **Hero / strip image** | 119–141 | `getPassHeroImageUri` or logo fallback |

### Loyalty Object (individual pass)

| What | Where (approx. lines) | Example |
|------|------------------------|--------|
| **Account name** | 184–185 | `setAccountName($customer->name ?? ...)` |
| **Stamp label** | 193 | `setLabel('Stamps')` |
| **Rewards label** | 203 | `setLabel('Rewards')` |
| **Barcode alternate text** | 218–221 | `'Scan to redeem'` / `'Scan to stamp'` |
| **Text modules** (progress, rewards, status, manual code) | 226–266 | `'header' => 'Progress'`, `'body' => ...`, etc. |

Change design by editing the strings passed to `setLabel`, `setProgramName`, `setIssuerName`, and the `'header'` / `'body'` arrays in `GoogleWalletPassService.php`.

### Images (Google)

- **Default logo:** `public/wallet/google/program-logo.png` (used in `getDefaultLogoUri()`).
- **Per-store:** Same Store model fields (`pass_logo_path`, `pass_hero_image_path`, `logo_path`) – URLs are built in the service.

---

## Global / config

- **Apple:** `config/passgenerator.php`  
  - `organization_name`, `pass_type_identifier`, `team_identifier`  
  - Certificate paths (for signing, not design).

- **Google:** `config/services.php` → `google_wallet`  
  - `issuer_id`, `class_id`, etc. (identity, not layout).

- **Store branding (both wallets):**  
  Store model: `background_color`, `brand_color`, `pass_logo_path`, `pass_hero_image_path`.  
  Change in DB or merchant dashboard; the two services read these automatically.

---

## Quick checklist

1. **Text / labels** → Edit `AppleWalletPassService.php` (pass definition) and `GoogleWalletPassService.php` (class + object text).
2. **Colors** → Store `background_color` / `brand_color` and/or fallbacks in `AppleWalletPassService.php` (lines 139–140); Google uses `setHexBackgroundColor` on the class.
3. **Images** → Replace `resources/wallet/apple/default/*.png` and/or `public/wallet/google/program-logo.png`; per-store images via Store uploads.

After changing code, regenerate or update a pass (e.g. re-add to wallet or trigger an update) to see the new design.
