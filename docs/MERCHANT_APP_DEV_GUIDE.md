# Guide for Merchant App Developer

Instructions for changing or improving the Flutter merchant app **without breaking** the Laravel backend integration.

---

## 1. What you MUST keep (don’t change or remove)

These are the contract with the backend. Changing them will break login, scanning, stamping, or redeeming.

| What | Why |
|------|-----|
| **API base URL** | Use `https://<your-domain>/api/v1` for all API calls (auth, stores, preview, stamp, redeem). |
| **Auth header** | Send `Authorization: Bearer <token>` on every request to `/api/v1/*`. |
| **Login** | `POST /api/v1/auth/login` with `email`, `password`, `device_name`. Store the returned `token`. |
| **Store list** | `GET /api/v1/stores` – use the returned `stores` array for the store picker. |
| **Preview** | `POST /api/v1/scanner/preview` with `token` (scan value) and `store_id`. Use the response’s **`public_token`** for stamp and **`redeem_token`** for redeem. |
| **Stamp** | `POST /api/v1/stamp` with **`token` = `public_token`** (from preview), or LA:… or 4‑char code. Never send an LR: / redeem token here. |
| **Redeem** | `POST /api/v1/redeem` with **`token` = `redeem_token`** (from preview), or LR:… |
| **Verify-email** | `POST /c/{public_token}/verify-email/send` at **site root** (no `/api/v1`), **no** Authorization header. Use `public_token` from the 422 redeem response or from preview. |
| **401 handling** | On 401, clear the stored token and send the user to login. |

As long as these stay the same, the app will keep working with the backend.

---

## 2. What you CAN change or improve (safe)

You can freely change or improve these without breaking the integration.

- **UI/UX** – Layout, colours, fonts, animations, navigation, loading states.
- **Copy and messaging** – Button labels, error messages (you can still show server messages when you get 422/409/404).
- **Store picker** – How you show the list (dropdown, list, search). Just keep using the same `GET /api/v1/stores` and send the chosen `store_id` in preview/stamp/redeem.
- **Scan flow** – Order of screens, confirmation dialogs, “Stamp” vs “Redeem” choice – as long as you still call preview first, then stamp with `public_token` or redeem with `redeem_token`.
- **Cooldown overlay** – You can use `cooldown_seconds` (e.g. 30) from the stamp success response for a countdown; first 5 seconds no override, then show “Override” and resend with `override_cooldown: true` if the user confirms. Changing how you show this (or not using it) is fine.
- **Verification modal** – How you show “Verification required” (text, buttons, “Send email” / “Stamp instead”). Just keep using `public_token` from the 422 response for the verify-email call and for “Stamp instead” (send that `public_token` to `POST /stamp`).
- **Optional request fields** – You can omit optional fields when you don’t need them (e.g. `idempotency_key`, `override_cooldown`, `quantity`) and let the backend use defaults. You can start sending them later without breaking anything.
- **Extra state/caching** – Caching store list, remembering last store, offline queue, etc. – all fine as long as when you hit the API you still send the right tokens and store_id.

---

## 3. What to AVOID (would break things)

| Don’t | Why |
|-------|-----|
| Send **LR:** or **redeem_token** to **POST /stamp** | Backend will treat it as redeem and may return verification_required; stamp will fail. |
| Send **public_token** or **LA:** to **POST /redeem** | Backend looks up by redeem_token; redeem will fail (invalid/expired). |
| Use **LA:** or **LR:** for **verify-email** | Endpoint expects **plain `public_token`** in the path. Use the value from preview or from the 422 `public_token` field. |
| Put verify-email under **/api/v1** or send **Bearer** auth | Route is at site root and is public; otherwise you’ll get 404 or auth errors. |
| Ignore **422** from redeem and not check **`status === 'verification_required'`** | You need that to show the verification modal and to read **`public_token`** for “Send email” and “Stamp instead”. |
| Change **request/response field names** the backend expects | e.g. `token`, `store_id`, `public_token`, `redeem_token`, `status`, `verification_required` – keep using the same names the backend sends and expects. |

---

## 4. Quick checklist after any change

Before releasing or handing over:

1. **Login** – Can log in and stay logged in (token stored and sent).
2. **Store picker** – Can load stores and select one; selected `store_id` is used in preview/stamp/redeem.
3. **Scan → Preview** – Scan (or enter code) and get customer/card info; response has `public_token` and `redeem_token`.
4. **Stamp** – Can stamp using `public_token` (or LA: / 4‑char); success shows updated stamp/reward info; 409 cooldown shows overlay and override works if you support it.
5. **Redeem** – Can redeem using `redeem_token` (or LR:); if backend returns 422 with `verification_required`, modal shows and “Send verification email” uses `public_token` in `/c/{public_token}/verify-email/send`, and “Stamp instead” uses `public_token` in `POST /stamp`.
6. **401** – On 401, token is cleared and user is sent to login.

If all of the above still work, your changes didn’t break the app.

---

## 5. Backend reference

- **API behaviour and routes:** See **`FLUTTER_MERCHANT_APP_INTEGRATION.md`** in this repo (or ask for the latest version).
- **Base URL:** Use the same domain as the web app (e.g. `https://app.kawhe.shop`); API prefix is `/api/v1`, verify-email is at root `/c/.../verify-email/send`.

If you need new API fields or endpoints, coordinate with the backend team so both sides stay in sync.
