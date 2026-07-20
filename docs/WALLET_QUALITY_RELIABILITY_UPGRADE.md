# Wallet Quality and Reliability Upgrade

**Implementation date:** 20 July 2026
**Status:** Local Stage 1 implementation complete; testing deployment and physical-device verification pending.

## Investigation

### Apple circular logo root cause

`AppleWalletPassService` deliberately called `createCircularLogoPng()` for every merchant wallet logo. That method used cover-style scaling into a square canvas and then made all pixels outside a circle transparent. Wide wordmarks were cropped before masking and square logos lost their corners.

### Previous image path

- Merchant uploads were stored in `pass_logo_path` and `pass_hero_image_path` through `StoreAssets`.
- Apple copied the source logo/hero into temporary pass files; only the logo received a destructive circular transform.
- Google used public URLs for the original uploads directly.
- Google stamp strips already used generated, hash-named images and were preserved.

### Previous refresh path

- Stamp and redemption transactions queued `UpdateWalletPassJob` after commit.
- The job called `WalletSyncService`, which attempted Apple push and Google object update independently.
- Apple web-service modification checks only considered `loyalty_accounts.updated_at`, so branding-only changes were invisible.
- Merchant branding saves did not consistently queue installed-pass refresh work.

### Previous retry limitations

- `UpdateWalletPassJob` used 3 attempts with short delays.
- `WalletSyncService` caught both provider failures and did not rethrow transient failures, so the queue considered failed provider calls successful.
- Repeated account syncs had no unique-job or overlap protection.
- Apple push batch failures were swallowed per registration.

### Previous preview limitations

Onboarding showed one Apple-style preview and labelled the same source assets as ready for Apple and Google. Loyalty-card edit had no platform preview or card-level provider health.

## Changes

### File inventory

Modified application files:

- `app/Http/Controllers/LoyaltyProgramController.php`
- `app/Http/Controllers/MerchantOnboardingWizardController.php`
- `app/Http/Controllers/StoreController.php`
- `app/Http/Controllers/Wallet/AppleWalletController.php`
- `app/Jobs/UpdateWalletPassJob.php`
- `app/Models/LoyaltyProgram.php`
- `app/Services/Wallet/Apple/ApplePushService.php`
- `app/Services/Wallet/AppleWalletPassService.php`
- `app/Services/Wallet/GoogleWalletPassService.php`
- `app/Services/Wallet/WalletSyncService.php`
- `app/Support/StoreAssets.php`
- `app/Support/StoreBrandingRules.php`
- `resources/views/components/wallet-pass-preview.blade.php`
- `resources/views/merchant/onboarding/wizard/card-design.blade.php`
- `resources/views/programs/partials/form.blade.php`
- `routes/console.php`
- `routes/web.php`

New application files:

- `app/Console/Commands/CleanupWalletAssets.php`
- `app/Jobs/RefreshProgramWalletsJob.php`
- `app/Rules/ValidWalletImage.php`
- `app/Services/Wallet/Artwork/AppleWalletArtworkRenderer.php`
- `app/Services/Wallet/Artwork/DecodedWalletImage.php`
- `app/Services/Wallet/Artwork/GoogleWalletArtworkRenderer.php`
- `app/Services/Wallet/Artwork/WalletArtworkResult.php`
- `app/Services/Wallet/Artwork/WalletArtworkService.php`
- `app/Services/Wallet/Artwork/WalletDesignHasher.php`
- `app/Services/Wallet/Artwork/WalletImageDecoder.php`
- `app/Services/Wallet/Artwork/WalletImageInspectionResult.php`
- `app/Services/Wallet/Artwork/WalletImageRenderer.php`
- `app/Services/Wallet/Artwork/WalletImageValidator.php`
- `app/Services/Wallet/WalletFailureClassifier.php`
- `app/Services/Wallet/WalletHealthService.php`
- `app/Services/Wallet/WalletPlatformException.php`
- `app/Services/Wallet/WalletPreviewDataFactory.php`

Test and documentation files:

- `tests/Feature/AppleWalletWebServiceTest.php`
- `tests/Feature/WalletArtworkServiceTest.php`
- `tests/Feature/WalletAssetCleanupTest.php`
- `tests/Feature/WalletPreviewAndHealthTest.php`
- `tests/Feature/WalletSyncReliabilityTest.php`
- `tests/Unit/WalletImageValidatorTest.php`
- `docs/DEVELOPER_HANDOVER.md`
- `docs/FULL_SYSTEM_DOCUMENTATION.md`
- `docs/WALLET_QUALITY_RELIABILITY_UPGRADE.md`
- `docs/WALLET_REAL_DEVICE_TEST_REPORT.md`

### Database migration

`2026_07_20_000001_add_wallet_design_metadata_to_loyalty_programs_table.php` adds:

- `wallet_design_version`
- `wallet_design_hash`
- `wallet_asset_manifest`
- `wallet_assets_generated_at`
- `wallet_branding_updated_at`

The migration is reversible and does not modify pass IDs, account IDs, balances, or source asset paths.

### Artwork layer

New classes under `app/Services/Wallet/Artwork`:

- `WalletArtworkService`
- `WalletDesignHasher`
- `WalletImageValidator`
- `WalletImageDecoder`
- `WalletImageRenderer`
- `AppleWalletArtworkRenderer`
- `GoogleWalletArtworkRenderer`
- Result/inspection DTOs

Generated Apple artwork:

- `logo.png` 160x50
- `logo@2x.png` 320x100
- `logo@3x.png` 480x150
- `strip.png` 375x144
- `strip@2x.png` 750x288
- `strip@3x.png` 1125x432

Apple logo rendering uses a transparent rectangular canvas, contain scaling, left-centre alignment, and padding. It never applies a circle mask.

Generated Google artwork:

- `program-logo.png` 660x660 with 15% transparent safe space
- `hero-image.png` 1032x812 with centre-cover cropping

Generated paths include platform, renderer version, derivative type, and design-hash prefix. Source uploads remain unchanged.

### Validation

`ValidWalletImage` and `WalletImageValidator` are shared by onboarding, store create/edit, and loyalty-card create/edit.

Hard failures cover unsupported/corrupt files, MIME mismatch, invalid dimensions, unsafe pixel count, animated WebP, and undecodable formats. Existing PNG/JPG/JPEG/WebP and 2 MB upload limits remain unchanged. Low resolution and extreme aspect ratios produce merchant-facing warnings instead of blocking existing artwork.

### Design refresh

The deterministic design hash includes visible colours, programme/store names, reward title/target, source content hashes, and renderer version. Identical saves do not increment the design version. Effective branding changes regenerate derivatives, retain the immediately previous manifest, and queue `RefreshProgramWalletsJob` after commit.

The bulk job processes loyalty accounts in chunks of 100 and dispatches the existing account job. Both jobs are unique and overlap-protected.

### Apple update state

Apple update endpoints now compare Wallet timestamps against the later of:

- loyalty-account `updated_at`
- loyalty-programme `wallet_branding_updated_at`

Branding-only changes therefore appear in device update lists and prevent incorrect `304 Not Modified` responses.

### Retry and failure isolation

`UpdateWalletPassJob` now uses:

- 5 attempts
- 60-second timeout
- backoff of 30, 120, 300, and 900 seconds
- unique account key
- `WithoutOverlapping`

Apple and Google are still attempted independently. Transient network, provider 5xx, and rate-limit failures are rethrown after both platforms have had a chance to run. Permanent configuration/credential failures are logged without indefinite retry. Merchant-safe categories and provider-specific results are written to `SupportAuditLog`; wallet tokens and credentials are not included.

Google audit metadata records class and object outcomes separately (`created`, `updated`, `unchanged`, or partial base/image results) while retaining the existing class and object identifiers. Diagnostic-detail collection is failure-isolated and cannot turn a successful provider update into a failed sync.

### Preview and health UI

Onboarding and loyalty-card edit now show separate Apple Wallet and Google Wallet previews. Existing fields update both previews without saving. The previews include realistic customer/progress/reward/manual-code examples and a platform-layout disclaimer.

Loyalty-card edit shows Apple/Google health states, generated artwork state, design version, installed Apple registration count, recent success time, and a rate-limited queued retry action. Credential readiness requires the configured local certificate/service-account file to exist and be readable; paths and credentials are never shown to merchants.

### Cleanup

`php artisan wallet:cleanup-assets` deletes stale, unreferenced generated files only. It retains the current and immediately previous manifests and never scans/deletes original merchant upload directories. It runs daily at 03:30 through Laravel's scheduler.

## Compatibility

The following contracts remain unchanged:

| Area | Result |
| --- | --- |
| Stamp logic | Unchanged |
| Redeem logic | Unchanged |
| Scanner/cooldowns/idempotency | Unchanged |
| Customer join and existing-card lookup | Unchanged |
| Email verification | Unchanged |
| Stripe billing and limits | Unchanged |
| Mobile API | Unchanged |
| Apple serial numbers | `kawhe-{loyalty_account_id}` unchanged; legacy resolution retained |
| Apple authentication tokens | Unchanged |
| Google class IDs | Unchanged |
| Google object IDs | Unchanged |
| Barcode formats | `LA:`, `LR:`, and `REDEEM:` compatibility unchanged |
| Manual codes | Unchanged |
| Printed join QR links | Unchanged |

## Automated Tests

Added coverage for:

- Apple/Google derivative dimensions and separation
- Apple rectangular transparency and visible square-logo corners
- Google circular-mask safe space
- Source upload preservation
- Design version changes and unchanged-save behaviour
- Cache-busting filenames
- Corrupt/MIME-spoofed/excessive-pixel validation
- PNG, JPEG, and WebP acceptance plus transparent-spacing warnings
- Tall-logo contain rendering and missing-source fallbacks
- Separate previews and card health
- Branding-only Apple update discovery
- Apple/Google failure isolation and transient retry
- Permanent failure handling
- Job retry/overlap settings
- Generated asset cleanup retention

Results recorded during implementation:

- Focused pre-change baseline: 41 passed, 2 skipped.
- Focused post-change regression: 65 passed, 2 skipped.
- New wallet tests: 29 passed, 2 skipped when run with Apple web-service tests.
- Full suite: 261 passed, 2 skipped, 7 unrelated existing failures (990 assertions).
- Frontend build: passed.

The two skipped tests require real Apple signing certificates. The unrelated full-suite failures are password-reset routes disabled in this app, two placeholder tests expecting `/` to return 200 instead of its intentional login redirect, and an existing store archive/restore middleware expectation.

## Deployment

### Testing rollout

1. Commit and push the reviewed branch.
2. Deploy the exact commit to the testing environment.
3. Run `php artisan migrate --force`.
4. Run `npm ci && npm run build` or the existing deployment script.
5. Run `php artisan optimize:clear` and rebuild production caches.
6. Restart queue workers so they load the new job retry/middleware code.
7. Confirm the scheduler is running for `wallet:cleanup-assets`.
8. Confirm the configured asset disk is publicly reachable over direct HTTPS and allows public PNG writes.
9. Confirm Apple signing/APNs and Google service-account configuration.
10. Complete `docs/WALLET_REAL_DEVICE_TEST_REPORT.md` before production.

### Storage requirements

The configured `filesystems.assets_disk` must support read, write, list, delete, last-modified, public visibility, MIME metadata, and direct public HTTPS URLs. DigitalOcean Spaces meets this through the existing S3 Flysystem adapter.

### Rollback

1. Stop/restart queue workers on the prior release to prevent workers running mixed job code.
2. Deploy the prior known-good commit.
3. Keep the migration columns in place during an emergency code rollback; the prior code ignores them safely.
4. Do not run `migrate:rollback` while new jobs may still be queued.
5. If schema rollback is later required, drain wallet jobs, deploy prior code, then roll back the migration.
6. Keep source uploads and generated current/previous manifests. Do not manually delete versioned derivatives during rollback.
7. Pass serials, auth tokens, Google IDs, QR links, and balances need no migration or restoration.

## Pending Work

- Testing-environment deployment is not performed in this local implementation.
- Apple physical-device checks are pending.
- Android/Google Wallet physical-device checks are pending.
- Existing installed-pass upgrade checks are pending.
- Real provider image-fetch and branding-refresh evidence is pending.

The upgrade must not be described as production-complete until those checks pass.
