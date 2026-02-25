# Debug Reverb / Real-Time Stamp Updates (testing.kawhe.shop)

When a stamp is applied (or redeemed), the backend broadcasts a `StampUpdated` event over **Laravel Reverb** (WebSockets). The **card page** (`/c/{public_token}`) uses **Echo** to subscribe to channel `loyalty-card.{public_token}` and update the UI when it receives the event. If stamps don’t update live on the web card, use this guide on **testing.kawhe.shop** (and adapt for production).

---

## 1. How it works (short)

- **Backend:** After a stamp/redeem, `StampUpdated::dispatch($account)` runs. Laravel’s broadcast driver (Reverb) sends the event to the Reverb **server** process.
- **Reverb server:** A long-running process (`php artisan reverb:start` or Supervisor) that holds WebSocket connections and forwards events to browsers.
- **Frontend:** The card page loads JS that creates `window.Echo`, subscribes to `loyalty-card.{public_token}`, and listens for `.StampUpdated`. When the event is received, it updates stamp count and UI.

If any of these is misconfigured or not running, the card won’t update in real time.

---

## 2. Server-side checklist (on testing server)

SSH into the testing server and run from the app root (e.g. `/var/www/kawhe-testing`).

### 2.1 Broadcast driver

Laravel must use the `reverb` broadcast connection:

```bash
cd /var/www/kawhe-testing
php artisan tinker
>>> config('broadcasting.default')
=> "reverb"
```

If you get `"null"` or `"log"`, set in `.env`:

```env
BROADCAST_CONNECTION=reverb
```

Then:

```bash
php artisan config:clear
php artisan config:cache
```

### 2.2 Reverb env vars (backend)

Backend needs these so it can **send** events to the Reverb server and so the Reverb **server** accepts connections:

```bash
grep -E '^BROADCAST_|^REVERB_' .env
```

You should see at least:

- `BROADCAST_CONNECTION=reverb`
- `REVERB_APP_ID=...`
- `REVERB_APP_KEY=...`
- `REVERB_APP_SECRET=...`
- `REVERB_HOST=...`   (e.g. `testing.kawhe.shop` – **same host as the site** so the browser can connect)
- `REVERB_PORT=...`   (usually `443` if Reverb is proxied behind Nginx on HTTPS)
- `REVERB_SCHEME=https`

If Reverb runs on a **different port** on the same host (e.g. 8080) and is **not** behind Nginx, then `REVERB_PORT=8080` and `REVERB_SCHEME=http` (and the frontend must be built with the same host/port; see 3.2).

### 2.3 Reverb server process

The Reverb **process** must be running. It listens on the port you configured (e.g. 8080 for the app, or Nginx proxies 443 to it).

Check if something is listening (example port 8080):

```bash
sudo ss -tlnp | grep 8080
# or
sudo lsof -i :8080
```

If nothing is listening, start Reverb (for debugging, in the foreground):

```bash
cd /var/www/kawhe-testing
php artisan reverb:start
```

Leave it running and try scanning again. If stamps now update, you need to run Reverb permanently (e.g. Supervisor). Example Supervisor config:

```ini
[program:kawhe-reverb]
command=php /var/www/kawhe-testing/artisan reverb:start
directory=/var/www/kawhe-testing
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/kawhe-testing/storage/logs/reverb.log
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start kawhe-reverb:*
```

### 2.4 Nginx (if Reverb is behind HTTPS)

If the site is served over HTTPS and you want the browser to connect to `wss://testing.kawhe.shop` (port 443), Nginx must proxy WebSocket to the Reverb process. Example (Reverb listening on 8080):

```nginx
# Inside server { ... } for testing.kawhe.shop
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://127.0.0.1:8080;
}
```

Reload Nginx after changes. Then `REVERB_HOST=testing.kawhe.shop`, `REVERB_PORT=443`, `REVERB_SCHEME=https` and the frontend should use the same (see 3.2).

### 2.5 Confirm event is dispatched (Laravel logs)

After a scan, the app logs the dispatch:

```bash
tail -100 storage/logs/laravel.log | grep -i StampUpdated
```

You should see something like:

```
Dispatching StampUpdated event (redeem) {"public_token":"...","channel":"loyalty-card.xxx","stamp_count":...}
```

If this line appears, the backend is dispatching. If not, the problem is earlier (stamp/redeem or broadcast driver). If it appears but the card still doesn’t update, the issue is Reverb process or frontend (next sections).

---

## 3. Frontend (browser) checklist

The card page loads `resources/js/app.js` (via Vite build). Echo is configured in `resources/js/bootstrap.js` using **Vite env** vars. Those are **baked in at build time**, so the **built** assets on the server must have been compiled with the correct Reverb URL for testing.

### 3.1 Is Echo enabled?

On the card page (`https://testing.kawhe.shop/c/{public_token}`):

1. Open DevTools (F12) → **Console**.
2. Reload the page.
3. Look for:
   - `Echo configured: { host: ..., port: ..., secure: ..., key: 'set' }` → Echo is on and points to that host/port.
   - `VITE_REVERB_APP_KEY not set. Real-time updates disabled.` → Echo is disabled; frontend was built without `VITE_REVERB_APP_KEY`.
   - If you see neither, check that the card page really loads the built app JS (e.g. from `build/assets/...`).

### 3.2 Correct host/port in the build

Echo uses:

- `VITE_REVERB_HOST` (default `localhost`)
- `VITE_REVERB_PORT` (default 443 if scheme is https)
- `VITE_REVERB_SCHEME` (default https)
- `VITE_REVERB_APP_KEY` (must match `REVERB_APP_KEY`)

So the **build** that is deployed to testing must have been run with something like:

```env
VITE_REVERB_APP_KEY=your-app-key
VITE_REVERB_HOST=testing.kawhe.shop
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

If you built with `VITE_REVERB_HOST=localhost` or a different port, the browser will try to connect to the wrong place. Fix: set the correct `VITE_*` in `.env`, run `npm run build`, and redeploy the built assets to the server.

### 3.3 WebSocket connection in the browser

In DevTools → **Network** tab, filter by **WS** (WebSocket). Reload the card page. You should see a WebSocket request to your Reverb URL (e.g. `wss://testing.kawhe.shop/app?...`). If it fails (red, 4xx, or “pending” then disconnect), the problem is:

- Reverb process not running, or
- Wrong host/port in the build, or
- Nginx not proxying `/app` (or your path) to Reverb, or
- Firewall blocking the port.

### 3.4 Event received in console

When the event is received, the card page logs:

```text
Stamp Updated event received: { stamp_count: ..., ... }
```

- Open the card in the browser, then trigger a stamp (scan) from the scanner or another tab.
- Watch the console. If you see this log, Echo and Reverb are working and the UI should update. If the UI doesn’t update, the bug is in the frontend `updateUI` logic. If you never see this log, the event is not reaching the browser (Reverb or Nginx or wrong channel).

---

## 4. Quick test: dispatch event from Tinker

On the server:

```bash
cd /var/www/kawhe-testing
php artisan tinker
```

Then (replace `PUBLIC_TOKEN` with a real card `public_token`):

```php
$account = \App\Models\LoyaltyAccount::where('public_token', 'PUBLIC_TOKEN')->first();
if ($account) {
    \App\Events\StampUpdated::dispatch($account);
    echo "Dispatched StampUpdated for " . $account->public_token . "\n";
} else {
    echo "Account not found\n";
}
```

With the card page open in the browser for that `public_token`, you should see in the console “Stamp Updated event received” and the UI should update. If it doesn’t, the problem is Reverb or the frontend connection. If it does, the pipeline works and the issue is likely that the event isn’t being dispatched on real scan (e.g. wrong broadcast driver or exception before dispatch).

---

## 5. Health check command

From the app root:

```bash
php artisan kawhe:health
```

This prints the broadcast driver and Reverb host/port. Use it to confirm server config.

---

## 6. Summary checklist (testing.kawhe.shop)

| Check | Command / Where |
|-------|------------------|
| Broadcast driver is `reverb` | `php artisan tinker` → `config('broadcasting.default')` or `.env` `BROADCAST_CONNECTION=reverb` |
| Reverb env vars set | `grep -E '^REVERB_|^BROADCAST_' .env` |
| Reverb process running | `php artisan reverb:start` (foreground) or Supervisor, and `ss -tlnp \| grep 8080` (or your port) |
| Nginx proxies WebSocket (if HTTPS) | `location /app { ... proxy_pass http://127.0.0.1:8080; }` and reload Nginx |
| Laravel dispatches event | `tail -100 storage/logs/laravel.log \| grep StampUpdated` after a scan |
| Frontend Echo configured | Browser console on card page: “Echo configured” and correct host/port |
| Frontend built with correct URL | Build with `VITE_REVERB_HOST=testing.kawhe.shop`, then deploy assets |
| WebSocket connects | DevTools → Network → WS → request to Reverb URL |
| Event received in browser | Console: “Stamp Updated event received” when stamping or after tinker dispatch |

Most “stamps don’t update on the web card” issues on testing are either **Reverb process not running** or **frontend built with wrong Reverb host/port**. Start with 2.3, 2.4, and 3.1–3.3.

---

## 7. Still not updating: run these in order

Reverb listening on **127.0.0.1:8080** is only reachable **on the server**. The browser runs on the user's device and must connect via the **public URL** (e.g. `wss://testing.kawhe.shop`). That requires **Nginx to proxy** WebSocket traffic to 127.0.0.1:8080.

### Step 1 – Server: broadcast driver

```bash
cd /var/www/kawhe-testing
php artisan tinker --execute="echo config('broadcasting.default');"
```

Must print `reverb`. If it prints `null` or `log`, add to `.env`: `BROADCAST_CONNECTION=reverb`, then `php artisan config:clear && php artisan config:cache`.

### Step 2 – Server: Reverb env (public URL)

```bash
grep -E '^BROADCAST_|^REVERB_HOST|^REVERB_PORT|^REVERB_SCHEME' .env
```

You want: `BROADCAST_CONNECTION=reverb`, `REVERB_HOST=testing.kawhe.shop`, `REVERB_PORT=443`, `REVERB_SCHEME=https`. So the browser connects to the same host as the site.

### Step 3 – Nginx: WebSocket proxy for `/app`

The frontend connects to path `/app`. Nginx must proxy that to Reverb. Check:

```bash
sudo grep -r "8080\|/app" /etc/nginx/sites-enabled/ 2>/dev/null || sudo grep -r "8080\|/app" /etc/nginx/conf.d/ 2>/dev/null
```

If there is **no** `location /app` with `proxy_pass http://127.0.0.1:8080` and WebSocket headers, add it to the `server { ... }` block for `testing.kawhe.shop` (see section 2.4), then:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### Step 4 – Browser: what the card page is using

Open the card page (`https://testing.kawhe.shop/c/{token}`). In **Console** look for `Echo configured:` and note **host** and **port** (should be `testing.kawhe.shop` and `443`). If you see `localhost` or wrong host, the frontend was built with wrong `VITE_REVERB_*`; rebuild and redeploy. In **Network** → **WS**: check if a WebSocket to `wss://testing.kawhe.shop/...` exists and succeeds.

### Step 5 – Rebuild frontend if host/port were wrong

In your **local** `.env` (or build env) set:

```env
VITE_REVERB_APP_KEY=<same as REVERB_APP_KEY on server>
VITE_REVERB_HOST=testing.kawhe.shop
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Then `npm run build`, deploy `public/build` to the server, and test again.
