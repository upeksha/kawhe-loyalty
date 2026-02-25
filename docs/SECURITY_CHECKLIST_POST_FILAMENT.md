# Security Checklist – After Removing Filament

This checklist confirms that **data and access control** remain correct after removing Filament. All checks below are satisfied by the current codebase.

---

## 1. Route-level protection (middleware)

| Area | Middleware | Effect |
|------|------------|--------|
| **Merchant** (`/merchant/*`) | `auth` + `EnsureMerchantHasStore` | Must be logged in; users with no stores are redirected to onboarding (except store CRUD routes, which are exempt so they can create first store). |
| **Admin** (`/admin/*`) | `auth` + `SuperAdmin` | Only users with `is_super_admin` can access. |
| **Scanner actions** (stamp, redeem, preview) | `auth` + `verified` | Must be logged in and email verified. |
| **Profile / Billing** | `auth` | Authenticated only. |
| **API** (`/api/v1/*` except login) | `auth:sanctum` (+ `verified` for scanner) | Bearer token required; invalid/expired token → 401. |
| **Wallet pass download** | `signed` | URL must be signed; tampering invalidates. |
| **Verify-email send** | No auth (by design) | Throttled (3 per 10 min); CSRF-exempt for mobile app. |

**Conclusion:** Sensitive routes are behind the correct auth and role middleware. No Filament-specific middleware was required for security.

---

## 2. Store data – only owner (or super admin) can access

| Action | How it’s enforced |
|--------|-------------------|
| **List stores** | `Store::queryForUser(Auth::user())` → only current user’s stores (or all for super admin). |
| **Edit store** | `Store::queryForUser(Auth::user())->whereKey($store->id)->firstOrFail()` → 404 if not owner. |
| **Update store** | Same as edit. |
| **View store** (show) | `$this->authorize('view', $store)` → `StorePolicy::view` → `$user->id === $store->user_id`. |
| **Delete store** | `$this->authorize('delete', $store)` → policy checks ownership. |
| **QR / QR PDF** | Explicit check: `$store->user_id !== Auth::id() && !Auth::user()->is_super_admin` → 403 if not owner and not super admin. |

**Conclusion:** Store access is scoped to the owning user (or super admin where intended). No cross-tenant access.

---

## 3. Customer / loyalty account data – only merchant’s stores

| Action | How it’s enforced |
|--------|-------------------|
| **List customers** | `LoyaltyAccount::whereIn('store_id', $storeIds)` with `$storeIds = Auth::user()->stores()->pluck('id')`. Optional `store_id` filter is checked: `$storeIds->contains($storeId)` or 404. |
| **Show / Edit / Update customer** | `$storeIds->contains($loyaltyAccount->store_id)`; otherwise `abort(404, '...')`. |

**Conclusion:** Customers (loyalty accounts) are only visible/editable for stores belonging to the logged-in merchant.

---

## 4. Scanner / stamp / redeem (web and API)

- **Preview:** Verifies the requested store is one of the user’s: `Auth::user()->stores()->where('id', $requestedStoreId)->first()`; 403 if no access. Same for store resolved from the scanned account.
- **Stamp / Redeem:** Use authenticated user and store ownership checks inside the controller; stamp/redeem are only for the merchant’s stores.
- **API** (`/api/v1/...`): Same controllers; `auth:sanctum` ensures the request is for the authenticated user. No change after Filament removal.

**Conclusion:** Scanner and stamp/redeem remain protected by auth and store ownership.

---

## 5. Admin dashboard

- **Route:** `auth` + `SuperAdmin` middleware.
- **Controller:** `AdminDashboardController@index` – reads global stats (User::count(), Store::count(), etc.). Access is restricted to super admins only.

**Conclusion:** Admin area is restricted to super admins; no regression from Filament removal.

---

## 6. Public and semi-public endpoints (by design)

| Endpoint | Access | Notes |
|----------|--------|--------|
| **Card view** (`/c/{public_token}`) | Anyone with token | Access is the unguessable `public_token`; no auth required. |
| **Join flows** (`/join/...`) | Public | Needed for customers to join; rate limiting and validation in place. |
| **Verify-email send** | No auth | For mobile app; throttled and CSRF-exempt. |
| **Stripe webhook** | Cashier signature | Verified by Cashier. |
| **Apple Wallet** (`/wallet/v1/...`) | `ApplePassAuthMiddleware` | Pass-specific auth, not session. |

**Conclusion:** These are intentionally public or specially protected; unchanged by Filament removal.

---

## 7. Policies and authorization

- **StorePolicy:** Used by `StoreController` for `view` and `delete`. Policies are auto-discovered (Laravel convention: `Store` → `StorePolicy`).
- **Store create/update:** Not using policy; access controlled by `queryForUser` and `firstOrFail` (404 if not owner).

**Conclusion:** Store policy is in use where needed; controller-level checks cover edit/update/QR.

---

## 8. Quick verification commands (optional)

On the server or locally:

```bash
# No Filament packages
composer show | grep -i filament
# Expect: no output

# No Filament routes
php artisan route:list | grep -i filament
# Expect: no output

# Merchant routes use web controllers
php artisan route:list --path=merchant
# Expect: StoreController, MerchantCustomersController, ScannerController, closures – no Filament
```

---

## Summary

| Check | Status |
|-------|--------|
| Merchant routes require auth + store check | OK |
| Admin routes require super admin | OK |
| Store data scoped to owner (or super admin for QR) | OK |
| Customer data scoped to merchant’s stores | OK |
| Scanner/stamp/redeem use auth and store ownership | OK |
| API uses Sanctum; 401 for invalid token | OK (unchanged) |
| Signed/special middleware for wallet and webhooks | OK |
| No Filament packages or routes left | OK (verify after deploy) |

**Conclusion:** After removing Filament, **data and access control remain secure**. All sensitive actions are behind the correct middleware and controller-level checks; store and customer data are scoped to the authenticated user’s stores.
