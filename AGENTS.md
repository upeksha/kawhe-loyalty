# AGENTS.md

## Cursor Cloud specific instructions

### Product

**Kawhe Loyalty** — Laravel 12 PWA for merchant loyalty programs (stores, customer cards, QR stamping, optional Reverb WebSockets, Stripe billing, Apple/Google Wallet). Single app in this repo; the Flutter merchant scanner is a separate client of `/api/v1`.

### System prerequisites (not in update script)

The `composer.lock` requires **PHP >= 8.4** (Symfony 8 components). Ubuntu 24.04 needs the [ondrej/php](https://launchpad.net/~ondrej/+archive/ubuntu/php) PPA and packages such as `php8.4-cli`, `php8.4-sqlite3`, `php8.4-mbstring`, `php8.4-xml`, `php8.4-curl`, `php8.4-zip`, `php8.4-bcmath`, `php8.4-intl`, `php8.4-gd`. Composer is expected at `~/bin/composer` with `export PATH="$HOME/bin:$PATH"`.

### First-time app setup (once per clone)

See `README.md` and `setup-local.sh`. Minimum:

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
```

`setup-local.sh` also sets `MAIL_MAILER=log` and clears caches.

### Running the app

| Service | Command | Notes |
|---------|---------|--------|
| HTTP | `php artisan serve --host=0.0.0.0 --port=8000` | Required |
| Reverb | `php artisan reverb:start` | Optional; live card updates |
| Queue | `php artisan queue:work` | Needed when `QUEUE_CONNECTION=database` |
| Vite HMR | `npm run dev` | Optional; use `npm run build` if not running |

`composer run dev` starts serve + queue + pail + Vite together (Reverb still separate).

### Lint / test / build

| Task | Command |
|------|---------|
| Tests | `composer test` (uses in-memory SQLite; no running services) |
| PHP format check | `./vendor/bin/pint --test` |
| PHP format fix | `./vendor/bin/pint` |
| Frontend build | `npm run build` |

Pint may report existing style drift in the repo; that does not block tests.

### Gotchas

- **PHPUnit** (`phpunit.xml`) uses `QUEUE_CONNECTION=sync` and `BROADCAST_CONNECTION=null` — tests do not need Reverb or a queue worker.
- **Sessions/cache** default to `database` in `.env.example`; migrations must be applied before browsing authenticated routes.
- **Stripe / SendGrid / wallet certs** are optional for core merchant + customer flows; use `MAIL_MAILER=log` locally.
- Re-running `npm install` after `composer run dev` is already running may require restarting the Vite process to pick up new deps.
