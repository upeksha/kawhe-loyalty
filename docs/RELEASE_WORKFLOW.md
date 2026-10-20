# Kawhe Release Workflow

## Goal

Keep every change off the live app until it has been built and checked in a separate environment.

Standard path:

1. Local development
2. Testing deployment: `https://testing.kawhe.shop`
3. Production deployment: `https://app.kawhe.shop`

## Environments

### Local
- Purpose: build, iterate, run tests, and review code
- Data: local only
- Do not use live merchant data here

### Testing
- URL: `https://testing.kawhe.shop`
- Server path: `/var/www/kawhe-testing`
- Environment: `APP_ENV=testing`
- Database: separate from production
- Purpose: end-to-end product testing before release

### Production
- URL: `https://app.kawhe.shop`
- Server path: `/var/www/kawhe`
- Environment: `APP_ENV=production`
- Database: live merchant/customer data
- Purpose: real customer traffic only

## Branch Rules

Recommended Git flow:

1. Create a feature branch locally: `feature/<short-name>` or `codex/<short-name>`
2. Commit the change, then test and build locally
3. Push the exact commit
4. Deploy its prebuilt package to testing
5. Approve the exact commit that passed testing
6. Deploy that exact commit SHA to production

Important rule:

- Production should receive the exact commit that already passed on testing.
- Do not make extra edits directly on the production server.

## Required Release Process

### 1. Local development

Use the repository's local packaging command after committing the candidate:

```bash
./ops/package-release-local.sh <commit-sha>
```

It runs the complete PHP test suite locally (using local PHP, or the local
`kawhe-php84-test:latest` Docker image), then `npm ci` and `npm run build` on
the Mac. It writes `output/releases/<sha>.tar.gz` and a SHA-256 file. This
directory is ignored by Git. The release contains the exact commit ID and
`public/build`; no `.env`, customer data, or private keys are packaged. Keep
the package for a possible frontend rollback. Run narrower tests while
developing, but the package requires the full suite to pass.

### 2. Deploy to testing

Push the candidate commit to GitHub, then run on the Mac:

```bash
./ops/deploy-prebuilt-local.sh testing <full-commit-sha>
```

The wrapper uploads the checked package and deployment helper to a private
temporary path, verifies its checksum and embedded commit, then deploys the
Git commit with the prebuilt assets. The server does not run npm or tests.
It still installs production PHP dependencies, runs migrations and refreshes
Laravel caches. Review migrations and take the required backups before using
this for a release that changes data or production operations.

### 3. Test on `testing.kawhe.shop`

Minimum smoke test:

1. Merchant login
2. Merchant dashboard
3. Store create/edit
4. Customer join flow
5. Scanner preview/stamp/redeem
6. Wallet pass generation/update if affected
7. Billing flow if affected

For risky changes also verify:

1. Queue-backed flows
2. Email delivery
3. Reverb/live updates
4. Migration results

### 4. Promote to production

Once testing is approved, deploy the **same package and commit SHA** from the Mac:

```bash
./ops/deploy-prebuilt-local.sh production <approved-full-commit-sha>
```

The wrapper refuses production unless testing currently runs that SHA. Deploy
only during the reviewed release window with fresh backups, worker handling,
and rollback plan. The package is staged under `/tmp/kawhe-release-<sha>` on
the server; it may be removed after the release is verified.

## Rules For Safe Releases

- Never test new features first on `app.kawhe.shop`
- Never share the production database with testing
- Never edit tracked app files directly on the production server
- Never deploy from an uncommitted local server state
- Never run npm builds or automated test suites on the droplet
- Never deploy without the matching locally tested release archive
- Never run unreviewed migrations first on production
- Never promote a different commit than the one approved on testing

## Server Expectations

Both server checkouts should stay clean:

```bash
git status --short
```

Expected result:

- no modified tracked files
- no unexpected untracked files in the app repo

If a server checkout is dirty, stop and clean that up before the next release.

## Handling Dirty Server State

If `git status --short` shows modified tracked files:

1. Stop deployment
2. Review why the file changed on the server
3. Move permanent changes back into Git
4. Remove generated or accidental files from the repo checkout
5. Re-run deployment only after the checkout is clean

If the server checkout is dirty, investigate that specific drift before a
release; do not force the deployment.

## Suggested Ownership Model

- Development happens locally
- Testing approval happens on `testing.kawhe.shop`
- Production deployment uses the tested commit SHA only
- Hotfixes still follow the same flow unless there is a true outage

## Suggested Release Checklist

Before testing:

1. Local code reviewed
2. Relevant tests passed
3. Build completed

Before production:

1. Testing deployment completed
2. Smoke test passed on `testing.kawhe.shop`
3. Approved commit SHA recorded
4. Production checkout confirmed clean
5. Backup / rollback plan understood

After production:

1. Login works
2. Merchant dashboard works
3. Scanner works
4. Queue/health check status reviewed
5. Logs reviewed for new errors
