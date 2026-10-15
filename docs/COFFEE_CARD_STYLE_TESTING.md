# Illustrated coffee card style (testing)

Card creation and editing, plus the onboarding card-design step, now save `card_design` (`classic` or `illustrated_coffee`). The coffee choice is enabled only when APP_ENV is testing and APP_URL is https://testing.kawhe.shop. Classic remains the default.

Coffee artwork uses forest green/cream, the merchant's existing logos, adaptive cup stamps through 20, and a compact progress bar for larger targets. Wallet fronts use bounded reward labels; full rewards remain available in card details. Balances and redemption rules are unchanged. Classic colours/banner remain stored when switching styles. Switching styles changes the Wallet design hash and queues existing program refreshes.

Migration 2026_10_16_220000 adds the choice to stores and programs and backfills only the five explicitly named testing QA programs. Default-program synchronization retains the choice. Onboarding requires logos, but does not require a separate banner for coffee artwork.

Build assets locally. Deploy code, run only this migration in testing, upload public/build, cache views and restart only the testing Wallet queue. Keep a private pre-migration store/program backup. Rollback can restore the previous code/asset manifest; the additive columns may remain. Production enablement requires a separate reviewed release.

Validation: targeted creation, editing, onboarding, authorization, style-switching, artwork and Wallet regression coverage. Full suite also includes two existing UxHardeningTest failures expecting removed store-edit and scanner UI labels; these files are unchanged by this feature. Physical iPhone stamp/reward updates for the artwork were confirmed by the user before adding the creation option.
