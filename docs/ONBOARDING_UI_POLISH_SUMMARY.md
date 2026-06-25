# Merchant Onboarding — UI & UX Summary

**Last updated:** June 2026

Summary of the **current** merchant onboarding wizard and related polish. For field-level and validation detail, see `MERCHANT_ONBOARDING_AND_CARD_DESIGN_FLOW.md`.

---

## Wizard structure (4 steps)

| Step | View | Layout |
|------|------|--------|
| 1 Store basics | `wizard/store-basics.blade.php` | `onboarding-layout` + `onboarding-step-layout` |
| 2 Card design | `wizard/card-design.blade.php` | Required branding + wallet assets; live preview |
| 3 Customer form | `wizard/customer-form.blade.php` | Shared `registration-form-config-editor` |
| 4 Card ready | `wizard/card-ready.blade.php` | QR, join link, iframe preview, complete CTA |

**Removed:** Step 5 `continue-trial` (route redirects to card-ready).

**Legacy:** Single-page `merchant/onboarding/store` replaced by wizard; POST redirects to wizard.

---

## Key components

| Component | Purpose |
|-----------|---------|
| `onboarding-layout` | Setup mode: logo, help, logout — **no merchant sidebar** |
| `onboarding-step-layout` | Progress (Step X of 4), back link, title, actions slot |
| `registration-form-config-editor` | Presets, field toggles, optional preview — used in wizard **and** program edit |
| `form-error-summary` | Server validation errors at top of wizard forms |

---

## Step highlights

### Store basics
- Reward-focused step 1; store name/address grouped
- Collapsed store details when pre-filled from registration
- Mini preview: “Buy X, get 1 free [reward]”

### Card design
- **All assets required** (logo, wallet logo, wallet hero, colors)
- Continue disabled until uploads present (client + server)
- Two-column preview on desktop

### Customer form
- Quick presets: Fastest / Balanced / Marketing-friendly
- Café recommendation callout
- Friction indicator + signup preview (desktop)
- Same field names as program edit: `{field}_enabled`, `{field}_required`

### Card ready
- Phone-frame iframe preview of live join URL
- Copy link, download poster, complete onboarding → store QR page

---

## Customer join polish (related)

| Page | Behavior |
|------|----------|
| `join/landing` | Primary CTA: get card; consistent card title/subtitle |
| `join/show` | Loading state on submit; `<x-input-error>` |
| `join/existing` | Email or phone lookup when phone enabled; loading state |
| `join/invalid` | Branded 404 for bad links |
| `card/show` | Welcome banner on first join; wallet nudge once per session |

---

## Manual QA checklist

### Wizard
- [ ] All 4 steps render on desktop and mobile
- [ ] Progress shows “Step X of 4”
- [ ] Back links work without breaking state
- [ ] Card design rejects submit without all three images + colors
- [ ] Customer form presets update toggles correctly
- [ ] Complete redirects to store QR with success message

### Join
- [ ] Invalid token shows branded invalid page
- [ ] New join shows loading on submit
- [ ] Existing lookup: email-only by default
- [ ] With phone enabled on card: phone lookup works
- [ ] Return visit does not repeat wallet nudge every time

### Programs
- [ ] Create/edit card uses same registration form editor as wizard
- [ ] Empty programs index shows CTA

---

## Regression targets

```bash
php artisan test --filter='MerchantOnboardingIntegrationTest|SelfServeSaasBaselineTest|RefactoredJoinFlowTest|JoinTest|LoyaltyProgramTest|StoreTest'
```
