# Terminal Commands to Run the Project

## Initial Setup (First Time Only)

```bash
# Navigate to project directory
cd "/Users/robertcalvert/Desktop/kawhe 2.0"

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file (if .env doesn't exist)
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Build frontend assets
npm run build
```

## Running the Project (Daily Use)

You need to run these commands in **separate terminal windows/tabs**:

### Terminal 1: Laravel Server
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan serve --port=8000
```
The app will be available at: `http://localhost:8000`

### Terminal 2: Laravel Reverb (WebSocket Server)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan reverb:start
```
This handles real-time updates for stamping and redemption.

### Terminal 3: Frontend Assets (Development Mode)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
npm run dev
```
This runs Vite in watch mode for hot-reloading CSS/JS changes.

### Terminal 4: ngrok (Optional - for External Access)
```bash
ngrok http 8000
```
This creates a public URL (e.g., `https://xxxxx.ngrok-free.app`) for testing on mobile devices or sharing.

**⚠️ Important for ngrok:** When accessing through ngrok, you need to **build the assets** first:
```bash
npm run build
```
This creates production-ready CSS/JS files that work through ngrok. The Vite dev server (`npm run dev`) won't work properly through ngrok URLs.

---

## Quick Start (All-in-One)

If you want to run everything with one command (using the dev script):

```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
composer run dev
```

This runs:
- Laravel server
- Queue worker
- Pail (logs)
- Vite dev server

**Note:** You'll still need to run Reverb separately in another terminal:
```bash
php artisan reverb:start
```

---

## Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=EnrollmentTest
```

---

## Troubleshooting

If you get port conflicts:
- Change the port: `php artisan serve --port=8001`
- Update `.env` if needed: `APP_URL=http://localhost:8001`

If Reverb fails:
- Check `.env` has `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
- Make sure port 8080 is available (or change in `.env`)

**Styles missing when using ngrok?**
- Build the assets: `npm run build`
- The Vite dev server doesn't work through ngrok URLs
- After building, refresh your ngrok URL

