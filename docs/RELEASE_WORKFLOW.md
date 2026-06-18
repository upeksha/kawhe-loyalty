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
2. Build and test locally
3. Push the branch
4. Deploy that branch, or a chosen commit from it, to testing
5. Approve the exact commit that passed testing
6. Deploy that exact commit SHA to production

Important rule:

- Production should receive the exact commit that already passed on testing.
- Do not make extra edits directly on the production server.

## Required Release Process

### 1. Local development

Before testing deployment:

```bash
php artisan test
npm run build
```

For smaller changes, run the narrowest relevant test target as well.

### 2. Deploy to testing

Run on the testing server checkout:

```bash
cd /var/www/kawhe-testing
APP_DIR=/var/www/kawhe-testing ./ops/deploy-release.sh <git-ref-or-commit>
```

Examples:

```bash
APP_DIR=/var/www/kawhe-testing ./ops/deploy-release.sh origin/feature/store-threshold-lock
APP_DIR=/var/www/kawhe-testing ./ops/deploy-release.sh 1a5e99cbfcfa8c6e15730f61689c79271d50edf3
```

Short wrapper:

```bash
cd /var/www/kawhe-testing
./ops/deploy-testing.sh <git-ref-or-commit>
```

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

Once testing is approved, deploy the same commit SHA to production:

```bash
cd /var/www/kawhe
APP_DIR=/var/www/kawhe ./ops/deploy-release.sh <approved-commit-sha>
```

Example:

```bash
APP_DIR=/var/www/kawhe ./ops/deploy-release.sh 1a5e99cbfcfa8c6e15730f61689c79271d50edf3
```

Short wrapper:

```bash
cd /var/www/kawhe
./ops/deploy-production.sh <approved-commit-sha>
```

## Rules For Safe Releases

- Never test new features first on `app.kawhe.shop`
- Never share the production database with testing
- Never edit tracked app files directly on the production server
- Never deploy from an uncommitted local server state
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

Current known issue to resolve:

- Production checkout has local drift and should be cleaned before relying on automated releases.

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
