# Laravel Backend – Flutter Merchant App Integration

This document confirms how the Laravel API is aligned with the **Flutter merchant scanner app** (login, store picker, scan → stamp/redeem, verification flow). Use it together with the Flutter technical integration doc.

---

## 1. Base URL and auth

| Item | Laravel |
|------|--------|
| **API prefix** | `/api/v1` (see `routes/api.php`). Full base URL: e.g. `https://app.kawhe.shop/api/v1`. |
| **Verify-email (public)** | Site root, **no** `/api/v1`: `POST /c/{public_token}/verify-email/send`. |
| **Auth** | Laravel Sanctum. App sends `Authorization: Bearer <token>`. |
| **401** | App should clear token and redirect to login. |

---

## 2. Auth and stores (API)

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| Login | `POST /api/v1/auth/login` | No | Body: `email`, `password`, `device_name` (optional). Response: `token`, `user`. |
| Session check | `GET /api/v1/auth/me` | Bearer | Response: `user` (id, name, email, is_super_admin, email_verified_at, stores_count). |
| Logout | `POST /api/v1/auth/logout` | Bearer | — |
| Stores | `GET /api/v1/stores` | Bearer | Response: `stores` array with `id`, `name`, `reward_target`, `reward_title`, `require_verification_for_redemption`. |

---

## 3. Token rules (stamp vs redeem)

- **Stamp** (`POST /api/v1/stamp`): Accepts `public_token`, or `LA:...`, or 4‑char manual code. **Never** requires email verification. If the app sends `public_token` (from preview) to stamp, it is treated as stamp only.
- **Redeem** (`POST /api/v1/redeem`): Accepts `redeem_token`, or `LR:...` / `REDEEM:...`. Enforces store-level verification when `require_verification_for_redemption` is true.
- **Preview** (`POST /api/v1/scanner/preview`): Always returns **`public_token`** and **`redeem_token`** so the app can call stamp with `public_token` and redeem with `redeem_token`.

---

## 4. Scan flow (backend behaviour)

1. **POST /api/v1/scanner/preview**  
   - Body: `token` (LA:/LR:/REDEEM:/4‑char), `store_id` (optional).  
   - 200: `success`, `has_rewards`, `reward_balance`, `reward_title`, `stamp_count`, `reward_target`, `customer_name`, `store_name`, `store_id`, `is_redeem_qr`, `token`, **`public_token`**, **`redeem_token`**.

2. **POST /api/v1/stamp**  
   - Body: `token` (public_token or LA: or 4‑char), `store_id`, `count`, `idempotency_key`, `override_cooldown` (optional).  
   - 200 success: `status: "success"`, `stampCount`, `rewardBalance`, `rewardTarget`, `customerLabel`, **`cooldown_seconds`** (30) for app countdown overlay.  
   - 200 duplicate: `status: "duplicate"`.  
   - 409 cooldown: `status: "cooldown"`, `seconds_since_last`, `cooldown_seconds`, `allow_override`, etc.

3. **POST /api/v1/redeem**  
   - Body: `token` (redeem_token or LR:), `store_id`, `quantity`, `idempotency_key`.  
   - 200: success and receipt.  
   - **422 verification required:**  
     `status: "verification_required"`, `message`, **`public_token`**, `customer_name`, `customer_email`, `loyalty_account_id`.  
     (No CSRF; app uses `public_token` for verify-email and “Stamp instead”.)

4. **POST /c/{public_token}/verify-email/send** (site root, no /api/v1)  
   - No auth, no body. `public_token` in path (URL-encoded).  
   - **CSRF:** This path is excluded from CSRF verification in `bootstrap/app.php` (`c/*/verify-email/send`) so the native app can POST without a CSRF token.

---

## 5. Laravel checklist (Flutter alignment)

- [x] **POST /stamp** accepts `public_token` (or LA: or 4‑char) and never requires email verification; does not route LR: to redeem.
- [x] **POST /redeem** accepts `redeem_token` (or LR:) and returns **422** with **`status: "verification_required"`** and **`public_token`** when verification is required.
- [x] **POST /scanner/preview** returns **`public_token`** and **`redeem_token`** in the response.
- [x] **POST /c/{public_token}/verify-email/send** is at **site root** (no `/api/v1`), public, and excluded from CSRF.
- [x] Auth (login, token, GET /auth/me, GET /stores) is shared with the web app (same credentials).

---

## 6. Routes used by the app

| Method & path | Purpose |
|---------------|--------|
| POST `api/v1/auth/login` | Login; app stores Bearer token. |
| POST `api/v1/auth/logout` | Logout. |
| GET `api/v1/auth/me` | Session check. |
| GET `api/v1/stores` | Store picker. |
| POST `api/v1/scanner/preview` | Resolve scan; get public_token, redeem_token, has_rewards, etc. |
| POST `api/v1/stamp` | Add stamps (public_token / LA: / 4‑char); 409 cooldown + override. |
| POST `api/v1/redeem` | Redeem (redeem_token / LR:); 422 verification_required with public_token. |
| POST `c/{public_token}/verify-email/send` | Send customer verification email (site root, no auth). |

All of the above are implemented and aligned with the Flutter merchant app integration spec.
