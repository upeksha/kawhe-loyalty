# Café signup visibility

The dedicated platform administrator uses `/admin/merchants`. Access requires a
verified super-admin account; merchant and staff logins cannot use this page.
The list shows active café owners, their stores, email verification, onboarding
progress, Free/Pro plan and recorded campaign. Dates use Pacific/Auckland.
Accounts without recorded campaign data are labelled unknown, not attributed
retroactively. Archived stores are excluded by the existing Store scope.

## Configuration and access

Set `ADMIN_SIGNUP_ALERT_EMAIL` to the approved platform owner's inbox and rebuild
the config cache. Invite a dedicated administrator with:

```sh
php artisan kawhe:invite-superadmin <admin-email>
```

This creates no café, uses an unknown random password, and emails password setup
and email verification links. The administrator chooses their password privately.
An existing merchant is not automatically promoted. `--resend` replaces the setup
links for an existing administrator when necessary.

## Email operations

New merchant registrations and confirmed merchant email verification create
unique outbox rows. The `emails` worker sends the notifications. Staff invitations
and dedicated administrator setup do not generate café acquisition alerts. The
minute scheduler recovers unsent notifications, with three attempts before an
administrator-visible failure. Registration continues if queue dispatch fails.

After fixing a mail delivery problem, retry exhausted notifications with:

```sh
php artisan admin:dispatch-signup-alerts --retry-failed
```

`sent_at` means the mail transport accepted the message, not proof of delivery to
an inbox. Inspect provider delivery events and confirm receipt separately. A
process failure after SMTP acceptance but before recording success can cause a
duplicate email; normal duplicate signup/verification events are deduplicated.

## Campaign tracking

Tag marketing links with `utm_source`, `utm_medium` and `utm_campaign`; optional
`utm_content` and `utm_term` are supported. The companion marketing-site change
preserves the first tagged visit in session storage and appends these tags only
to the merchant registration link. The Laravel web session retains that first
tagged visit for up to 30 days and saves it when registration succeeds. Switching
browser/device or clearing browser storage can lose attribution. No full referrer
URLs or arbitrary query parameters are stored. Existing sent emails without tags
cannot be attributed retrospectively.

## 9 November 2026 release

The user approved local validation followed by production; hosted testing was
previously taken offline. This release uses the locally tested exact Git SHA and
prebuilt assets rather than the obsolete hosted-testing wrapper gate. Run all PHP
tests and build locally with `ops/package-release-local.sh`, and run the targeted
growth tests on an isolated local MySQL database. No mobile changes, dependency
updates, on-server builds, test suites, or server upgrade are involved.

Before deployment, take and verify private database/artwork backups; save the
previous SHA, Vite build and environment file in a root-only rollback directory.
Push the candidate commit, upload its matching asset archive, then run
`ops/deploy-release.sh` with explicit `APP_DIR=/var/www/kawhe` and checksum.
Refresh queue workers after the caches are rebuilt. Verify health, access gates,
the admin invitation, email delivery, scheduler and marketing signup links.

The migration is additive. For rollback, restore the previous Git SHA and build,
restore only the previous admin-alert setting, refresh Laravel caches and workers,
and return the marketing domain to its previous Vercel deployment. Leave the new
table and nullable JSON column in place to preserve intervening signups. Do not
restore the entire database just to revert application code. Review retained
outbox rows before re-enabling delivery after a rollback.
