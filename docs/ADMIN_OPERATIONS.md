# Super admin operations dashboard — first local release

## Access and scope

Open `/admin/dashboard` as a verified super admin. New operations routes are GET-only and use the existing admin middleware. The dashboard has no edit, stamp, redeem, undo, resend, retry or delete actions. Existing support tools remain separate. This release is local only; no deployment or database migration is required.

Pages: operations overview, searchable stores, store details, transactions and Needs attention. Date filters use New Zealand calendar dates and accept at most 90 days. Filter context follows navigation between the reports.

## Sources and meaning

- Transactions come from `stamp_events`; totals sum quantity, not row count. Added, undone and net stamps are separate. Undo activity is dated when the undo happened.
- Unique customers means distinct customer records. Issued cards means loyalty-account memberships. These are not interchangeable counts of people.
- Store, programme, issued-card and reward-balance figures describe current records; new cards and transaction totals use the selected period. Captions distinguish them.
- Archived stores remain available through the archived filter and retain history.
- Recorded issues are a status allowlist from support events, not a count of unresolved incidents. Later retries may have succeeded.
- Queue figures are aggregates from the database queue across all stores and dates. No job payloads or raw exception messages are rendered.
- General application errors, server monitoring and provider email-delivery events are not connected in this release. Zero recorded issues is not a complete health assessment.

Test stores are classified only by the explicit `ADMIN_TEST_STORE_IDS` environment variable. Names and email addresses are not guessed. The local allowlist is `62,75,76,77,114,115,116`; it is not a production classification. Use All stores when reviewing the full local fixture set.

Summary results are cached for 60 seconds, while pages use private/no-store response headers. Lists are paginated. Reports omit public card tokens, manual codes, raw support metadata and raw queue payloads.

## Local acceptance steps

1. Sign in with the dedicated local operations admin; leave existing merchant roles unchanged.
2. Compare All stores and Test stores, select a period, and inspect the daily figures.
3. Search for Crafty Baker under Test stores, open its detail page and review its programmes, team and recent activity.
4. Open transactions, select Redeem, and check quantities and retained store/date filters.
5. Review Needs attention and its coverage notice; test the empty state with Test stores.
6. Repeat navigation at phone width. Tables scroll inside their containers.
7. Confirm a regular merchant cannot access the operations routes.

## Verification

Local regression selection: `AdminOperationsTest`, `SupportAuditLogTest`, `SuperAdminInfrastructureTest`, `RegistrationTest`, `StampRewardSafetyTest`, `StampLoyaltyServiceTest`, `StoreStaffPermissionsTest`.

Result on 21 November 2026: **73 tests passed, 385 assertions**. Coverage includes authorization, GET-only routes, unchanged business records across report requests, NZ date boundaries, quantity and undo totals, explicit test-store scope, archived history, pagination, caching, secret omission and existing signup/stamping/redemption/staff behaviour. Production capacity and provider/device delivery were not tested by this local release.

Browser checks covered desktop and 390px mobile layouts, filtering, store details, transaction filtering, empty states and mobile navigation. Local asset build passed. No staging or live changes were made.

## Plans and revenue extension

`/admin/revenue` adds owner-based Free/Pro counts, active Pro, trials, past-due subscriptions and scheduled cancellations. Owners are deduplicated across stores and use the app's existing `UsageService` entitlement rules. Owners without a store are outside these store-scoped reports. An owner with stores in both classifications appears once in each scope, so scope totals are not additive.

The overview also displays plan counts. No checkout, webhook, subscription or transaction processing was changed.

Optional read-only Stripe reporting uses `ADMIN_STRIPE_REPORTING=true` and the environment's existing Cashier key, or the separate reporting key described below. It is off by default. Non-production environments reject live keys. Local reporting is now connected to Kawhe sandbox through the separate reporting credential; checkout/webhook credentials remain unchanged. Do not copy a live key into local development.

`ADMIN_STRIPE_REPORTING_KEY` optionally supplies a separate server-only reporting credential without changing checkout or webhook credentials. Missing sandbox prices referenced by old local fixtures are counted as unpriceable subscriptions; paid invoice reporting remains available and the recurring estimate is labelled incomplete. Other provider failures still suppress financial totals. The approved existing sandbox restricted key is stored only in the ignored local `.env` (mode 0600). No live credentials or real payment data have been imported locally.

Financial definitions:

- Paid invoice total: `amount_paid` for known Kawhe subscription IDs, attributed by Stripe `status_transitions.paid_at` within the selected NZ dates. Includes tax and does not subtract refunds, credits or fees; it is not profit, recognised revenue or bank payouts. Older issued invoices paid during the selected period are included.
- Estimated monthly recurring revenue: current active subscription list price times quantity, normalised for monthly/yearly billing. Trials are excluded. This is before discounts, taxes and fees; unpriceable subscriptions are explicitly marked as missing from the estimate.
- Separate currencies are never combined. Sandbox figures are marked TEST DATA.
- GET-only provider reads are cached for 10 minutes; provider errors do not expose messages or credentials. Scans are capped at 500 paid invoices, 12 distinct prices and a short time budget. Incomplete scans suppress monetary totals instead of reporting a partial result as complete. The overview never makes Stripe API calls.

Extension verification on 21 November 2026: 84 tests passed, 511 assertions across admin reporting, billing lifecycle, plan entitlement, signup, transaction safety and staff permissions. Revenue fixtures cover currency separation, annual normalisation, trial exclusion, date boundaries, subscription ownership, pagination limits, failures, caching and blocked live credentials. No actual provider earnings were fetched locally.

## Connected sandbox acceptance — 21 November 2026

The user confirmed Kawhe sandbox and approved using an existing sandbox key in ignored local configuration. A synthetic customer, clearly labelled test product/price and NZ$10/month subscription were created in the sandbox through Stripe Workbench. Its first invoice was paid using Stripe's synthetic test payment method. No real money was charged and no customer email address was supplied to Stripe.

The matching new local owner is `sandbox-revenue-20261121@kawhe.test`, store ID 116 (`TEST Sandbox Revenue Cafe`). The existing subscription sync service stored only this new local owner's test subscription. Existing local users, stores, programmes, subscriptions, customers, issued cards, stamps and points transactions were compared by their original IDs and hashes and remained unchanged.

Open `/admin/revenue?scope=test&store_id=116&range=today` to inspect the isolated test owner. Revenue reporting can narrow to an owner through the existing `store_id` filter, retains it on filter submission, and separates its cache from other scopes. Current acceptance figures: **1 Pro account, 1 paid invoice, NZ$10.00 paid invoice total, NZ$10.00/month estimated recurring revenue**, with TEST DATA clearly shown. Exact receipt IDs and reconciliation output are in ignored `output/admin-revenue-qa/verified-result.json`.

The provider report used GET requests only. Synthetic sandbox setup and local fixture creation were separate from the read-only dashboard; no new write actions, migrations or changes to checkout, stamping, redemption or webhook processing were introduced. No staging/live environment was contacted or changed. The sandbox subscription is left active for preview and is not a real recurring charge.

Final regression selection after connection and scoped-report checks: **85 tests passed, 523 assertions**. Browser verification confirmed the source subscription active and the local figures above. Configuration and its pre-connection backup are ignored and mode 0600. No credentials belong in documentation or test receipts.
