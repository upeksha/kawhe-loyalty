# Self-Serve Launch Checklist

This is the final launch pass for the Kawhe self-serve SaaS product.

## Product Flows

- Merchant can sign up and complete the **4-step onboarding wizard** without developer help.
- Merchant must upload **logo, wallet logo, wallet hero, and colors** during setup (and when adding a store).
- Merchant can create loyalty cards (programs), brand them, and publish join QR codes per card.
- Customer can join from QR and get a card successfully.
- Existing customer can recover a card with **email** (or **phone** when that field is enabled on the card).
- Merchant can stamp and redeem without duplicate-trigger issues.
- Apple Wallet and Google Wallet passes can be added from a fresh card.
- Wallet passes update after stamp and redeem.

## Blocking States

- Archived store shows a clear join message instead of a dead-end error.
- Free-plan join limit shows a clear customer-facing blocked state.
- Merchant billing page clearly explains when new joins are blocked.
- Missing or invalid card links show a friendly recovery message (`join/invalid`).
- Verification resend limits show a clear cooldown message.
- Missing customer email shows a clear explanation and next step.

## Merchant Self-Serve Tools

- Store QR page shows wallet health and recommended next action.
- Store edit page shows wallet health and wallet repair actions.
- Merchant can queue wallet refresh for all cards in a store.
- Merchant can resend welcome email and verification email safely.
- Merchant support logs are accessible and filterable.
- Billing diagnostics and plan state are visible.

## Support / Admin

- Support audit log is recording wallet syncs, verification sends, billing issues, and manual support actions.
- Admin support log view is accessible and filterable.
- Admin dashboard shows repeated billing and wallet issue signals.
- Merchant customer detail page shows support timeline and wallet status.

## Operations

- `/up` returns `200`.
- Queue worker is running and processing default plus emails.
- Reverb is running and reachable.
- Local backups run on schedule.
- DB backups upload off-server.
- Discord alert webhook is active.
- Server reboot recovery has been tested.

## Release Checks

- iPhone merchant app tested with production API.
- Android merchant app tested with production API.
- Signed Android AAB builds successfully.
- iOS release build compiles.
- App Store / Play listing assets are ready.
- Sensitive credentials shared during setup have been rotated.

## Go / No-Go

Go only if:

- all product flows above pass on fresh test data
- wallet updates are confirmed on both Apple and Google
- blocked states look intentional and understandable
- billing can recover from a real blocked join state
- merchant can solve basic support issues without developer help

If one of those fails, fix it before launch rather than accepting a support burden on day one.
