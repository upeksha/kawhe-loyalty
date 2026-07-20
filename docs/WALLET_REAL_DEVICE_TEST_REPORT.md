# Wallet Real-Device Test Report

## Test Build

| Field | Value |
| --- | --- |
| Build/commit tested | `52dafaca44ee005955e048bd4c5a88144413b2a7` |
| Environment URL | `https://testing.kawhe.shop` |
| Test date | 20 July 2026 |
| Tester | Automated staging checks by Codex; physical-device tester pending |
| Test programme | Temporary `Wallet Testing Cafe` programme; removed after smoke testing |
| Test loyalty account | No physical-device account yet |

No physical-device testing was performed during this implementation. Automated rendering, feature, regression, build, deployment, and authenticated testing-environment checks are recorded in `docs/WALLET_QUALITY_RELIABILITY_UPGRADE.md`. Device checks below remain `PENDING` until a human records physical-device evidence.

## Testing Environment Smoke Test

| Check | Expected result | Actual result | Status |
| --- | --- | --- | --- |
| Deployment | Exact reviewed application commit deployed | `52dafac` deployed, built and migrated | PASS |
| Wallet editor | Apple and Google previews render independently | Both previews rendered with `Alex`, `4/8`, rewards and example QR | PASS |
| Artwork generation | Eight platform derivatives generated | Required Apple and Google dimensions verified | PASS |
| Design version | Visible colour change increments version once | Version changed from 1 to 2 | PASS |
| Cache busting | Visible design change creates new image paths | Google and Apple hash filenames changed | PASS |
| Rollback window | Previous derivative version retained | Current and previous Google logo both remained readable | PASS |
| Public images | Direct HTTPS, PNG MIME, no redirect | Direct `200 image/png`, no redirect | PASS |
| Cache headers | Immutable generated assets receive long cache policy | `public, max-age=31536000, immutable` | PASS |
| Wallet health | Generated state becomes current after save | Apple and Google changed from Processing to Not yet used | PASS |
| Queue state | Refresh remains asynchronous and drains | 0 pending and 0 failed jobs after smoke test | PASS |

## Apple Device

| Field | Value |
| --- | --- |
| iPhone model | PENDING |
| iOS version | PENDING |
| Existing pass installed before upgrade | PENDING |
| Screenshot references | PENDING |
| Overall result | PENDING |

### Apple checklist

- [ ] Create/edit a test loyalty programme.
- [ ] Upload a wide rectangular cafe logo and wallet hero image.
- [ ] Download and add a new Apple pass.
- [ ] Confirm the full logo is visible, rectangular, not stretched, and not circularly masked.
- [ ] Confirm `logo.png`, `logo@2x.png`, and `logo@3x.png` render clearly.
- [ ] Confirm the strip image displays and crops predictably.
- [ ] Confirm customer name, stamp progress, reward balance, QR, and manual code.
- [ ] Stamp the card and confirm the installed pass refreshes.
- [ ] Reach the reward target and confirm reward state/redeem QR.
- [ ] Redeem the reward and confirm the installed pass refreshes again.
- [ ] Change only background colour and confirm the installed pass refreshes.
- [ ] Change only wallet logo and confirm the installed pass refreshes.
- [ ] Change only wallet hero and confirm the installed pass refreshes.
- [ ] Confirm the pass remains installed during all branding updates.
- [ ] Test wide, square, tall, transparent, and low-resolution logos.
- [ ] Confirm missing source artwork produces a usable fallback pass.
- [ ] Inspect Add to Wallet, collapsed list, expanded pass, and relevant lock-screen presentation.
- [ ] Inspect relevant light/dark device appearance differences.

## Android Device

| Field | Value |
| --- | --- |
| Android model | PENDING |
| Android version | PENDING |
| Google Wallet app version | PENDING |
| Existing card saved before upgrade | PENDING |
| Screenshot references | PENDING |
| Overall result | PENDING |

### Google checklist

- [ ] Save the same loyalty card to Google Wallet.
- [ ] Confirm the programme logo remains inside Google's circular safe area.
- [ ] Confirm the logo is not pre-masked or clipped incorrectly.
- [ ] Confirm hero image, background colour, customer name, stamps, rewards, barcode, and manual code.
- [ ] Stamp the card and confirm Google Wallet updates.
- [ ] Reach the reward target and confirm reward state updates.
- [ ] Redeem a reward and confirm the object updates.
- [ ] Change only background colour and confirm the class updates.
- [ ] Change only wallet logo and confirm the image URL changes and Wallet refreshes.
- [ ] Change only wallet hero and confirm the hero URL changes and Wallet refreshes.
- [ ] Confirm no duplicate loyalty class is created.
- [ ] Confirm no duplicate customer object is created.

## Existing-Pass Upgrade

| Check | Result | Evidence/notes |
| --- | --- | --- |
| Existing Apple pass refreshes without re-add | PENDING | |
| Existing Google card refreshes without re-add | PENDING | |
| Apple serial remains valid | PENDING | Automated compatibility test passes; device confirmation pending |
| Google class/object IDs remain valid | PENDING | Code contracts unchanged; provider confirmation pending |
| Existing QR and manual codes remain valid | PENDING | Automated regression tests pass; device confirmation pending |
| Existing stamp/reward balances remain unchanged | PENDING | Automated regression tests pass; device confirmation pending |

## Outstanding Defects

None recorded from automated wallet tests. Physical-device visual or provider-refresh defects cannot be ruled out until this report is completed on the testing environment.
