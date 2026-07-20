# UX Hardening Implementation Report

**Implementation date:** 20 July 2026

**Branch:** `codex/prod-hardening`

**Base commit:** `f234be7ead0fedd807f5808298e631bc551d8d24`

**Status:** Implemented and locally verified. Staging and physical-device verification remain pending.

## Baseline

The pre-change full suite reported 261 passed, 2 skipped, and 7 failed tests. The recorded failures were the four disabled password-reset route tests, the two root-route status assertions, and the Store restore redirect assertion. Browser review also found Store/Card configuration duplication, competing join actions, a dense customer card hierarchy, scanner state ambiguity, and inconsistent submit feedback.

Code inspection confirmed that `LoyaltyProgram` is the customer-facing card source of truth. `Store` retains legacy/fallback reward and branding fields for onboarding and compatibility. `LoyaltyAccount` resolves its programme values first and safely falls back to Store data.

## Store And Card Clarity

- Store edit now focuses on Store name, address, archive state, readiness, and loyalty-card navigation.
- The default card summary shows status, reward, customer count, and direct edit/QR/all-card actions.
- Legacy Store branding remains stored and compatible but is presented as read-only fallback information.
- Card edit is organised into Card details, Reward, Branding, Apple and Google Wallet, Status, and Customer sign-up form sections.
- Store context, breadcrumb hierarchy, default-card status, and the locked reward-target explanation are explicit.
- No Store/default-card sync rules or stored fallback fields were removed.

## Customer Experience

- Join landing prioritises `Join loyalty card`, keeps `Find my card` secondary, and shows the reward and no-app message above the fold.
- Join and existing-card forms preserve server validation, configured fields, lookup scope, and routes while adding autocomplete, mobile keyboard hints, error summaries, and protected loading states.
- Customer card now leads with `N of N stamps`, stamps remaining or reward-ready guidance, a scannable QR with quiet space, and the manual code.
- Verification explains that stamping remains available, provides resend guidance, and does not make an active unverified card appear disabled.
- Initial recent activity is limited to 10 customer-friendly events.

## Scanner

- The selected Store and current scanner state are prominent.
- The web camera has one start path, a larger mobile frame, clear scan guidance, and an always-available manual entry path after the scanner is revealed.
- Card confirmation shows customer/card context, current progress, rewards available, and one safe primary action.
- Actions expose processing states, prevent repeated clicks, use live-region feedback, translate common failures, and provide `Scan next customer`.
- A malformed Blade-forwarded Alpine transition attribute found during browser testing was removed; the fresh scanner page produces no new console error.
- Scanner controllers, routes, ownership checks, row locking, calculations, cooldown, duplicate protection, idempotency, token parsing, queue dispatch, broadcasts, and mobile API contracts were not changed.

## Wallet Communication

- Card editing labels separate Apple Wallet and Google Wallet previews.
- Live unsaved-image previews continue using temporary object URLs, which are now revoked when replaced and on page exit.
- The interface states that providers control final spacing and typography.
- Merchant-safe delayed/failure wording explicitly states that loyalty balances remain safe.
- Existing queued retry architecture, provider isolation, artwork derivatives, IDs, serials, authentication tokens, and field mapping remain unchanged.

## Shared UI And Accessibility

- Shared buttons support action-specific loading text and global double-submit prevention with stable width and `aria-busy`.
- Shared alerts support processing feedback and live-region semantics.
- Error summaries use `Please check the highlighted fields.` consistently.
- Merchant navigation adds labelled controls, Escape handling, focus return, and `x-cloak`; merchant and customer layouts prevent page-level horizontal overflow.
- Customer inputs remain at least 16px on narrow screens, QR sizing is responsive, and scanner/customer actions use mobile-friendly touch targets.

## Compatibility

The following remain unchanged: join URLs, printed QR codes, join short codes, customer matching, programme-scoped lookup, stamp calculations, reward calculations, redemption rules, verification requirements, scanner cooldown, duplicate protection, scanner idempotency, manual codes, Apple serials, Apple authentication tokens, Google class IDs, Google object IDs, billing rules, Stripe integration, and mobile API contracts.

Token and identifier formats remain compatible:

- `LA:{public_token}`
- `LR:{redeem_token}`
- `REDEEM:{redeem_token}`
- `kawhe-{loyalty_account_id}`

## Verification

Added `tests/Feature/UxHardeningTest.php` to pin Store/Card separation, card editor structure, join hierarchy and invalid-input preservation, customer-card hierarchy, and scanner recovery language. Updated customer-card copy assertions in `RewardTest` and `EnrollmentTest`.

Files changed:

- `app/Http/Controllers/CardController.php`
- `app/Http/Controllers/StoreController.php`
- `app/Services/Wallet/WalletHealthService.php`
- `resources/css/app.css`
- `resources/css/customer.css`
- `resources/js/app.js`
- `resources/views/card/show.blade.php`
- `resources/views/components/customer-layout.blade.php`
- `resources/views/components/form-error-summary.blade.php`
- `resources/views/components/merchant-layout.blade.php`
- `resources/views/components/ui/alert.blade.php`
- `resources/views/components/ui/button.blade.php`
- `resources/views/components/wallet-pass-preview.blade.php`
- `resources/views/join/existing.blade.php`
- `resources/views/join/landing.blade.php`
- `resources/views/join/show.blade.php`
- `resources/views/programs/partials/form.blade.php`
- `resources/views/scanner/index.blade.php`
- `resources/views/stores/edit.blade.php`
- `tests/Feature/EnrollmentTest.php`
- `tests/Feature/RewardTest.php`
- `tests/Feature/UxHardeningTest.php`
- `docs/DEVELOPER_HANDOVER.md`
- `docs/FULL_SYSTEM_DOCUMENTATION.md`
- `docs/UX_HARDENING_IMPLEMENTATION_REPORT.md`

Executed locally:

- Focused Store/Card/onboarding/wallet tests: 45 passed, 235 assertions.
- Focused join/customer tests: 19 passed, 67 assertions.
- Focused UX/reward/wallet tests: 16 passed, 93 assertions.
- `npm run build`: passed.
- PHP syntax checks: passed.
- Blade view compilation: passed.
- Pint and `git diff --check`: passed after scoped formatting.

Browser checks covered desktop (1440px), tablet (768px), 390px mobile, and 320px mobile. Store edit, card edit, join, customer card, scanner, customers, support, and billing showed no page-level horizontal overflow. The customer QR remained inside the 320px viewport and card previews rendered at tablet width.

Final `php artisan test` result: 266 passed, 2 skipped, and the same 7 baseline failures (1,029 assertions). No new failure remained. The known failures are:

- Four `PasswordResetTest` cases for password-reset routes that are disabled in the current application.
- `ExampleTest` and `HardeningTest`, which expect `/` to return 200 while the application redirects with 302.
- `StoreArchiveFlowTest`, which expects Store edit after restore while onboarding middleware redirects the fixture merchant to Store basics.

## Pending Manual Verification

The following were not claimed as complete locally and must be tested on staging before production:

- Full merchant registration and onboarding walkthrough.
- Real image upload and generated Apple/Google derivative review.
- Real phone camera allowed/blocked, QR scan, cooldown, duplicate, wrong-Store, and network-failure flows.
- Real customer join, verification email, Apple Wallet, and Google Wallet flows on supported phones.
- Queue worker health and support-log review after real wallet retries.

## Rollback

This batch contains no migration or destructive data operation. Roll back by reverting the UX-hardening commit, rebuilding frontend assets, and clearing Laravel caches. Existing Store/Card data, loyalty balances, wallet identifiers, QR codes, and API contracts require no data rollback.
