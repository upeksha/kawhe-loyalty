# AGENTS.md - Kawhe Loyalty Codex Workflow

## Project Overview
Kawhe Loyalty is a SaaS loyalty platform for merchants and customers. Merchants configure stores and rewards, onboard customers, scan to stamp/redeem rewards, and manage billing. Customers join via links, track progress, and can optionally use Apple Wallet or Google Wallet passes.

This file defines project-specific guidance for Codex agents. It is for planning, implementation, reviews, and safe delivery practices. Do not treat this file as product requirements; treat it as delivery guardrails.

## Tech Stack (Detected From Repo)
- Backend: PHP 8.2+, Laravel 12 (`composer.json`)
- Auth/API: Laravel Sanctum, Laravel Breeze
- Billing: Laravel Cashier (Stripe)
- Real-time: Laravel Reverb + Laravel Echo + Pusher JS client
- Frontend: Blade, Vite, Tailwind CSS, Alpine.js, Flowbite
- Data: Laravel migrations + Eloquent (SQLite locally; MySQL/PostgreSQL expected for production)
- Async: Laravel jobs/queues
- Tests: Pest + PHPUnit (`php artisan test`)
- Wallet integrations: Apple Wallet passgenerator + Google API client

## Folder Structure Summary
- `app/` Application logic (controllers, services, models, events, jobs, policies, middleware)
- `app/Support/` Shared validation/helpers (e.g. `RegistrationFormConfig`, `StoreBrandingRules`, `StoreAssets`)
- `routes/` Web, API, auth, channels, console routes
- `resources/views/` Blade UI pages and reusable components
- `resources/js/`, `resources/css/` Frontend assets
- `database/migrations/` Schema history
- `database/factories/`, `database/seeders/` Test/development data utilities
- `tests/Feature/`, `tests/Unit/` Automated tests
- `config/` App and integration configuration
- `docs/` Operational and implementation documentation — **start at `docs/DEVELOPER_HANDOVER.md` for current product behavior**
- `public/` Built/static web assets and manifest/service worker files
- `ops/` Operational helper scripts

## Common Commands
### Setup
- `composer install`
- `npm install`
- `cp .env.example .env`
- `php artisan key:generate`
- `php artisan migrate`

### Run (development)
- `php artisan serve --port=8000`
- `php artisan reverb:start`
- `npm run dev`
- Optional all-in-one dev runner: `composer run dev` (still run Reverb separately)

### Build
- `npm run build`

### Test
- `php artisan test`
- `php artisan test --filter=<TestName>`
- `composer test`

### Lint/Formatting
- `vendor/bin/pint`

### Migrations
- `php artisan migrate`
- `php artisan migrate:status`
- `php artisan migrate --pretend`

## Coding Rules
- Make minimal, scoped changes aligned to the task.
- Preserve established Laravel conventions in this repo (controllers in `app/Http/Controllers`, service/domain logic in `app/Services`, validation in form requests or controller validation, authorization in policies/middleware).
- Avoid broad refactors unless explicitly requested.
- Do not rename public routes, request/response keys, queue names, or event names without explicit approval.
- Keep comments concise and only where logic is non-obvious.
- Never touch unrelated files.

## Database Rules
- Every schema change must be a migration; do not edit historical migrations that already shipped.
- Use foreign keys, indexes, and sensible constraints for integrity and performance.
- Preserve idempotency and auditability patterns used in stamping/redemption flows.
- Consider lock/concurrency behavior for writes (especially loyalty account counters and rewards).
- Respect tenant/store ownership boundaries in queries.

## Frontend/UI Rules
- Follow existing Blade component patterns in `resources/views/components`.
- Ensure responsive behavior for merchant dashboard, scanner, onboarding, and customer card pages.
- Include empty/loading/error states for async UX.
- Keep UI text clear and consistent with existing terminology (store, loyalty account, reward, stamp).
- Avoid backend or route contract changes from frontend tasks unless explicitly requested.

## Backend/API Rules
- Keep route contracts stable, especially under `/api/v1` and scanner/redeem flows.
- Validate all input; enforce authorization for store-scoped actions.
- Maintain idempotency and duplicate-scan protections.
- Keep side effects explicit (events/jobs/mail/webhooks).
- For Stripe and webhooks, require signature verification and ownership checks.

## Security Rules
- Never expose or log secrets: API keys, `.env` values, DB credentials, Stripe keys, wallet keys, SMTP credentials.
- Never commit `.env`, secrets, or credential-like artifacts.
- Enforce authz/authn for privileged actions.
- Guard against mass assignment, insecure file uploads, and over-broad API responses.
- Apply rate limiting on abuse-prone endpoints.

## Testing Expectations
- Add/update tests for behavior changes (Feature tests for flows, Unit tests for pure logic where useful).
- Validate happy path + failure path + authorization path for new endpoints.
- For bug fixes, include a regression test when practical.
- Do not claim tests passed unless they were executed.

## Git and Change Management
- Keep diffs small and atomic.
- Do not revert unrelated working tree changes.
- Do not run destructive commands (`git reset --hard`, forced deletes) unless explicitly requested.
- Document assumptions and risks in PR/change notes.

## Definition Of Done
- Scope implemented exactly as requested.
- No unrelated file changes.
- Security and authorization impact reviewed.
- Tests added/updated where applicable and executed when possible.
- Commands and rollout/migration notes included if deployment impact exists.
- Output is understandable for the next engineer with clear file references.

## Suggested Sub-Agent Workflow For New SaaS Features
1. `product-architect` (read-only): break request into vertical slices with acceptance criteria.
2. `database-designer` (read-only): validate schema/data implications and migration plan.
3. `security-reviewer` (read-only): identify authz/data exposure risks before implementation.
4. `backend-developer` (workspace-write): implement API/domain logic + backend tests.
5. `frontend-designer` (workspace-write): implement UI/UX flows + component updates.
6. `stripe-billing-agent` (read-only by default): review billing/webhook impacts; switch to write mode only when explicitly asked.
7. `qa-reviewer` (read-only): run structured review (Critical/Important/Nice to have).
8. `devops-deployment-reviewer` (read-only): check deploy safety, migrations, queues, env readiness, rollback notes.
