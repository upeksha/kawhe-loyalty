# Kawhe Go-Live Checklist

Use this checklist before advertising and pushing the iOS app to users. Backend: **https://app.kawhe.shop/** (DigitalOcean + MySQL).

---

## 1. Backend (DigitalOcean / Laravel)

### Environment & security
- [ ] **APP_ENV=production** and **APP_DEBUG=false** on the server.
- [ ] **APP_URL=https://app.kawhe.shop** (no trailing slash). Used for links in emails and asset URLs.
- [ ] **APP_KEY** set and never rotated without planning (sessions/tokens).
- [ ] **.env** not in git; only on server or in secure secrets.

### Database
- [ ] MySQL configured (DB_CONNECTION=mysql, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD).
- [ ] Automated **backups** (DigitalOcean managed DB backups or cron + mysqldump to object storage).
- [ ] **Queue worker** running (`php artisan queue:work`) so verification emails and other jobs run (e.g. supervisor or systemd).

### Mail (required for verify-email flow)
- [ ] **MAIL_*** configured (e.g. SendGrid, Postmark, Resend, or SMTP).**
- [ ] **MAIL_FROM_ADDRESS** and **MAIL_FROM_NAME** set (e.g. `noreply@app.kawhe.shop` or your domain).
- [ ] Test: trigger “Send verification email” from a loyalty card and confirm email arrives and links use **https://app.kawhe.shop**.

### SSL & domain
- [ ] Site served over **HTTPS** (certificate valid; no mixed content).
- [ ] **SANCTUM_STATEFUL_DOMAINS** (if used) includes your web domain; API token auth works without this for mobile.

### Optional but recommended
- [ ] **LOG_LEVEL=warning** or **error** in production; **LOG_CHANNEL** with rotation (e.g. daily).
- [ ] **Rate limiting** on login and public endpoints (Laravel throttle middleware; verify-email already throttled).
- [ ] **Uptime/monitoring** (e.g. UptimeRobot, Pingdom) for https://app.kawhe.shop and optionally /api/v1/auth/login (HEAD/GET if allowed).

---

## 2. iOS app configuration

### API base URL
- [ ] App uses **API base URL**: `https://app.kawhe.shop/api/v1` (no trailing slash).
- [ ] Verify-email is called at **https://app.kawhe.shop/c/{public_token}/verify-email/send** (no /api/v1, no auth).

### Build for release
- [ ] Release build uses **production** API URL (no dev/local URLs).
- [ ] Test full flow on device: **Login → Stores → Scan (preview) → Stamp and Redeem** (including verification required → send verify email → stamp instead).

### App Store / TestFlight (choose one path)
- [ ] **TestFlight:** Build uploaded, beta testers added, test login with real backend.
- [ ] **App Store:** App Store Connect app created; metadata, screenshots, privacy policy URL, and submission completed.

---

## 3. App Store submission (if going to public App Store)

- [ ] **App name, subtitle, description** final.
- [ ] **Screenshots** for required device sizes (e.g. 6.7", 6.5", 5.5").
- [ ] **Privacy policy URL** (hosted e.g. at https://app.kawhe.shop/privacy or your marketing site).
- [ ] **Support URL** (e.g. https://app.kawhe.shop/support or support email).
- [ ] **Sign in with Apple** (if you use it) configured in App Store Connect and in the app.
- [ ] **App category** and **keywords** set.
- [ ] **Pricing** (free/paid) and **availability** (countries) set.
- [ ] Submit for review; respond to any reviewer questions.

---

## 4. Website / landing (https://app.kawhe.shop)

- [ ] **Homepage** clearly explains: what Kawhe is, who it’s for (merchants + customers), and **download / App Store link**.
- [ ] **Merchant sign-up / login** works (register, login, create store).
- [ ] **Customer flow** works: open loyalty card link, stamps, redeem, verify email if required.
- [ ] Remove or replace default Laravel “Let’s get started” content if it’s still the main message (so visitors see a proper product page).

---

## 5. Before you advertise

- [ ] **Smoke test:** Register → create store → get loyalty card link → open in browser → add stamp (e.g. from Filament/merchant scanner or API) → redeem (with/without verification).
- [ ] **iOS app + live backend:** Login in app → pick store → scan or enter code → stamp → redeem (and verify-email path if you use it).
- [ ] **Support channel:** Decide where users will get help (email, in-app, or website) and add that to the app and site.

---

## 6. Launch / marketing next steps

1. **Landing page**  
   - One clear headline, short description, and **App Store / TestFlight** button.  
   - Optional: “For merchants” (web login) and “For customers” (download app / open card link).

2. **App Store listing**  
   - Strong description and screenshots.  
   - If TestFlight first: share TestFlight link; if public: share App Store link.

3. **Who you’re targeting**  
   - Merchants (cafes, small shops) and/or end customers.  
   - Tailor message: “Stamp loyalty for your shop” vs “Collect stamps and redeem rewards.”

4. **Channels**  
   - Social (Instagram, Facebook, LinkedIn depending on audience).  
   - Local business groups, coffee/shop communities.  
   - Simple press or blog post with link to app and https://app.kawhe.shop.

5. **Feedback and iteration**  
   - Use TestFlight feedback or support email to fix critical issues before scaling.

---

## Quick reference: live URLs

| Purpose              | URL |
|----------------------|-----|
| Website              | https://app.kawhe.shop |
| API base (for app)    | https://app.kawhe.shop/api/v1 |
| Verify-email (no auth)| https://app.kawhe.shop/c/{public_token}/verify-email/send |

Once every item relevant to your launch is checked, you’re ready to go live and promote the app.
