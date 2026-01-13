# Quick Start - Run Locally Without Errors

## ✅ Pre-Flight Check

I've configured your app for local development. Here's what's set:

- ✅ **APP_ENV**: `local` (for development)
- ✅ **APP_DEBUG**: `true` (shows errors)
- ✅ **APP_URL**: `http://localhost:8000`
- ✅ **Migrations**: All run
- ✅ **Assets**: Built
- ✅ **Caches**: Cleared

## 🚀 Start the App (4 Terminal Windows)

### Terminal 1: Laravel Server
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan serve --port=8000
```
**Visit**: http://localhost:8000

### Terminal 2: Reverb (WebSocket - for real-time updates)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan reverb:start
```
**Note**: Real-time updates will work on localhost

### Terminal 3: Queue Worker (for emails)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
php artisan queue:work
```
**Note**: Required if emails are queued (currently set to send synchronously in local)

### Terminal 4: Frontend Dev Server (Optional - for hot-reload)
```bash
cd "/Users/robertcalvert/Desktop/kawhe 2.0"
npm run dev
```
**Note**: Only needed if you want hot-reload. Assets are already built.

## 📧 Email Configuration

Currently set to **log driver** (emails written to logs):
- Check emails in: `storage/logs/laravel.log`
- To switch back to SendGrid: Change `MAIL_MAILER=smtp` in `.env`

## ✅ Verify Everything Works

1. **Visit**: http://localhost:8000
2. **Register** a merchant account
3. **Create** a store
4. **Test** the join flow
5. **Test** scanning/stamping

## 🐛 If You See Errors

### Database Errors
```bash
php artisan migrate
```

### Asset Errors
```bash
npm run build
```

### Cache Issues
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Permission Errors
```bash
chmod -R 775 storage bootstrap/cache
```

## 📝 Current Configuration

- **Environment**: Local
- **Debug Mode**: Enabled
- **Email**: Log driver (check logs)
- **WebSocket**: Enabled (works on localhost)
- **Queue**: Database (but emails send synchronously in local)

## 🎯 Quick Test

1. Start all 4 terminals
2. Visit http://localhost:8000
3. Register → Create Store → Get Join Link
4. Open join link in new tab
5. Create customer → View card
6. Scan QR code from scanner

Everything should work without errors! 🎉
