# Merchant Onboarding v2 – UI Polish Summary

This document summarizes the UI/UX polish applied to the Merchant Onboarding v2 wizard. **No backend logic, routes, validation, or DB schema were changed.**

---

## Files changed or added

### New components
- **`resources/views/components/onboarding-step-layout.blade.php`** – Reusable wizard layout: progress bar (Step X of 5), optional Back link, title/subtitle, content slot, optional sticky actions slot.
- **`resources/views/components/onboarding-helper-note.blade.php`** – Small helper text for form fields.
- **`resources/views/components/onboarding-form-section.blade.php`** – Section wrapper with optional uppercase label (e.g. "Store details", "Reward setup").

### Updated wizard views
- **`resources/views/merchant/onboarding/wizard/store-basics.blade.php`**
  - Uses `onboarding-step-layout`; title "Create your first loyalty card", subtitle about café rewards.
  - Fields grouped into "Store details" (name, address) and "Reward setup" (stamps needed, reward title).
  - Helper text: "Most cafés use 8–10 stamps", examples for reward title.
  - Right-side mini preview: "Buy X, get 1 free [reward]" (reactive with Alpine).
  - Same form `action`, field names, and validation behavior.

- **`resources/views/merchant/onboarding/wizard/card-design.blade.php`**
  - Uses `onboarding-step-layout` with Back to Store Basics.
  - Two-column layout (desktop): form left, **live preview panel** right.
  - Sections: "Brand colors", "Store branding", "Wallet assets (Optional)" with Optional badge.
  - Color inputs + hex text inputs kept in sync (Alpine).
  - File uploads: dropzone-style labels ("Choose file"), current image previews when present.
  - Preview panel: card mock with store name, reward title, logo (if set), stamp circles, brand/background colors; optional "Apple Wallet / Google Wallet ready" note when pass assets exist.
  - Same `action`, field names, and file handling.

- **`resources/views/merchant/onboarding/wizard/customer-form.blade.php`**
  - Uses `onboarding-step-layout` with Back to Card Design.
  - Title "Choose what customer details to collect", subtitle about keeping sign-up quick.
  - "Recommended for cafés" callout (email + first name + birthday, shorter forms).
  - Email row locked (always required).
  - Optional fields as cards with Enabled checkbox and Required checkbox (shown only when enabled).
  - Right-side "Signup form preview" listing which fields are enabled/required.
  - Same `action` and field names (`*_enabled`, `*_required`).

- **`resources/views/merchant/onboarding/wizard/card-ready.blade.php`**
  - Uses `onboarding-step-layout` with Back to Customer Form.
  - Title "Your loyalty card is ready", subtitle "Start collecting customers today."
  - Success-style block: "Your first 50 customer cards are free" / "No setup delay."
  - QR code in a clear card; join link + Copy button.
  - Buttons: Download poster (PDF), Open test join page, **Continue trial** (primary CTA).
  - "How it works" sidebar: 3 steps (scan QR → save card → stamp and reward).
  - Same QR data, join URL, poster URL, and advance/continue behavior.

- **`resources/views/merchant/onboarding/wizard/continue-trial.blade.php`**
  - Uses `onboarding-step-layout` with Back to Card Ready.
  - Title "Launch your digital stamp card", subtitle about 50 cards included.
  - Short copy: "Start free with 50 customer cards", dashboard/branding note.
  - Single primary CTA: "Get started" (same POST to complete).
  - No backend or route changes.

---

## Design system notes
- **Progress:** Step X of 5 + full-width progress bar.
- **Cards:** Rounded-2xl where appropriate, soft borders (stone-200), clear hierarchy.
- **Copy:** Coffee/café-focused; "Free coffee", "flat white" style examples; "50 customer cards" trial messaging.
- **Responsive:** Grids collapse to single column on small screens; preview panels move below form; buttons remain tappable.
- **Accessibility:** Labels on inputs, helper text, focus rings on buttons/inputs, Optional/Required indicated by text as well as styling.

---

## Manual QA checklist (UI)

Use this to verify the polished onboarding flow.

### General
- [ ] Each step (1–5) renders without errors on desktop and mobile.
- [ ] Progress bar shows correct step (e.g. "Step 2 of 5") and fills accordingly.
- [ ] Back links go to the previous step and do not break the flow.
- [ ] Validation errors from the server display clearly (e.g. required field missing).

### Step 1: Store Basics
- [ ] "Create your first loyalty card" and subtitle are visible.
- [ ] Store details and Reward setup sections are clearly grouped.
- [ ] Helper text appears (e.g. "Most cafés use 8–10 stamps", reward title examples).
- [ ] Right-side preview updates when changing "Stamps needed" and "Reward title" (e.g. "Buy 9, get 1 free coffee").
- [ ] Submit "Continue" creates the store and redirects to Step 2.

### Step 2: Card Design
- [ ] Two-column layout on desktop; single column on mobile with preview below form.
- [ ] Brand color and Background color: picker and hex input stay in sync when editing either.
- [ ] Store logo upload: "Choose file" works; current logo shows if already set.
- [ ] Wallet assets section is clearly marked Optional; pass logo and pass hero uploads work.
- [ ] Preview panel shows store name, reward title, logo (if any), stamp circles, and updates when colors change.
- [ ] Submit "Continue" saves design and redirects to Step 3.

### Step 3: Customer Form
- [ ] "Recommended for cafés" callout and "Shorter forms usually get more signups" are visible.
- [ ] Email is shown as always required (no toggle).
- [ ] First name, Last name, Phone, Birthday: toggling "Enabled" shows/hides the "Required" option.
- [ ] When a field is disabled, the "Required" option is not shown (and not submitted).
- [ ] Right-side preview list reflects which fields are enabled/required.
- [ ] Submit "Continue" saves config and redirects to Step 4.

### Step 4: Card Ready
- [ ] Success-style message and "Your first 50 customer cards are free" are visible.
- [ ] QR code displays and matches the store’s join URL.
- [ ] Join link input is read-only; Copy button copies the link (and shows feedback if implemented).
- [ ] "Download poster (PDF)" opens/downloads the PDF.
- [ ] "Open test join page" opens the join URL in a new tab.
- [ ] "Continue trial" button is the main CTA and advances to Step 5.

### Step 5: Continue Trial
- [ ] "Launch your digital stamp card" and 50-cards messaging are visible.
- [ ] "Get started" submits and completes onboarding; redirect goes to store QR page with success message.
- [ ] After completion, merchant can access dashboard and normal merchant flows.

### Responsive
- [ ] On narrow viewports, step content stacks (no horizontal scroll); preview panels sit below forms.
- [ ] Color and file inputs remain usable on mobile (tap targets, readable labels).

### Unchanged behavior (regression)
- [ ] All form `action` URLs and field names are unchanged (no 419 or validation errors from renamed fields).
- [ ] File uploads still save to the same paths (logos, pass-logos, pass-heroes).
- [ ] QR poster PDF URL and join link logic are unchanged.
- [ ] Final redirect after "Get started" goes to the store QR page as before.
