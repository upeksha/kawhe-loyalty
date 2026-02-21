# Using app.kawhe.shop as API Base URL

The canonical API and web app domain is **app.kawhe.shop**. Use **https://app.kawhe.shop/api/v1** as the API base URL for the mobile app.

---

## 1. Backend (Laravel / this app)

The host is controlled by **where you deploy** and **.env**, not by code. The app uses `config('app.url')` everywhere (emails, wallet, links).

### Server and DNS

- Point **app.kawhe.shop** to the server that runs this Laravel app (A/CNAME in DNS).
- Configure your web server (e.g. nginx) so that the Laravel app is served for `app.kawhe.shop` (and HTTPS with a valid cert).

### .env on the server

Set:

```env
APP_URL=https://app.kawhe.shop
```

(No trailing slash. This drives links in emails, wallet pass URLs, and asset URLs.)

### Optional: Sanctum (cookie / web auth)

If you use Laravel Sanctum with cookie-based auth for a web dashboard, add the domain to stateful domains in **.env**:

```env
SANCTUM_STATEFUL_DOMAINS=app.kawhe.shop
```

For **API token auth** (mobile app with `Authorization: Bearer <token>`), no Sanctum domain config is required.

### No code changes

- API routes stay under `/api/v1` (unchanged).
- Verify-email stays at `/c/{public_token}/verify-email/send` (site root).
- All generated URLs come from `APP_URL`; just set it to `https://app.kawhe.shop`.

---

## 2. Mobile app (Flutter)

Update the app to use **app.kawhe.shop** instead of **testing.kawhe.shop**.

### URLs to use

| Purpose        | URL |
|----------------|-----|
| API base       | `https://app.kawhe.shop/api/v1` |
| Verify-email   | `https://app.kawhe.shop/c/{public_token}/verify-email/send` |

### What to change in the Flutter app

1. **API base URL**  
   Set the base URL for all API calls (login, stores, preview, stamp, redeem) to:
   - **`https://app.kawhe.shop/api/v1`**  
   (no trailing slash.)

   Typical places this is defined:
   - A config file (e.g. `lib/config/api_config.dart`, `lib/core/constants.dart`)
   - Environment variables or `--dart-define=API_BASE_URL=https://app.kawhe.shop/api/v1`
   - A `.env` or `config.json` read at runtime

2. **Verify-email endpoint**  
   When the app calls “Send verification email”, it must POST to:
   - **`https://app.kawhe.shop/c/{public_token}/verify-email/send`**  
   (site root, no `/api/v1`, no Bearer token.)

   So the “base URL” for this single request is **`https://app.kawhe.shop`**; path is **`/c/{public_token}/verify-email/send`**.

3. **Rebuild and test**  
   - Build a new release/TestFlight build with the updated base URL.
   - Test: login, store list, scan → preview → stamp, scan → redeem, and “Send verification email” (then check email link uses `https://app.kawhe.shop`).

### Example (pseudo-config)

If your app has something like:

```dart
// Before
static const String apiBaseUrl = 'https://testing.kawhe.shop/api/v1';
static const String siteBaseUrl = 'https://testing.kawhe.shop';

// After
static const String apiBaseUrl = 'https://app.kawhe.shop/api/v1';
static const String siteBaseUrl = 'https://app.kawhe.shop';
```

Then:
- All API calls: `apiBaseUrl + '/auth/login'`, etc.
- Verify-email: `siteBaseUrl + '/c/$publicToken/verify-email/send'`.

---

## 3. Quick checklist

**Backend (app.kawhe.shop server):**

- [ ] DNS: `app.kawhe.shop` → your server
- [ ] HTTPS and certificate in place
- [ ] `.env`: `APP_URL=https://app.kawhe.shop`
- [ ] Optional: `SANCTUM_STATEFUL_DOMAINS=app.kawhe.shop` if using web cookie auth

**Mobile app:**

- [ ] API base URL = `https://app.kawhe.shop/api/v1`
- [ ] Verify-email URL = `https://app.kawhe.shop/c/{public_token}/verify-email/send`
- [ ] New build and full flow test

**Marketing site (kawhe.shop):**

- [ ] “Log in” links to `https://app.kawhe.shop/login`
- [ ] “Register” / “Start free trial” links to `https://app.kawhe.shop/register`
