# Welcome / verification email delivery – improving speed

If welcome or verification emails feel slow to arrive, some of that is under your control (queue and worker) and some is not (SendGrid and recipient inbox).

## What you can improve (app side)

### 1. Use the prioritized queue and worker

Welcome and verification emails are sent to the **`emails`** queue. Run your queue worker so it processes that queue first and checks often:

```bash
php artisan queue:work database --queue=emails,default --sleep=1 --tries=3
```

- **`--queue=emails,default`** – Emails are processed before other jobs (e.g. wallet updates).
- **`--sleep=1`** (or `--sleep=0`) – Worker checks for new jobs every 1 second instead of 3+, so new emails are picked up quickly.

If you use Supervisor or systemd, update the command there to include `--queue=emails,default` and `--sleep=1`. See [PRODUCTION_EMAIL_SETUP.md](PRODUCTION_EMAIL_SETUP.md).

### 2. Optional: send welcome emails synchronously

To remove queue delay entirely, send welcome and verification emails during the request:

In `.env`:

```env
MAIL_WELCOME_SYNC=true
```

- **Effect:** Emails are sent immediately (no queue). They reach SendGrid as soon as the request completes.
- **Tradeoff:** The registration/verification page may take 1–3 seconds longer to respond.
- **When to use:** If you want the fastest “leave our server” time and can accept slightly slower page loads.

Default is `false` (emails are queued to `emails`).

## What is outside your control

- **SendGrid handoff** – After your app (or worker) sends the message over SMTP, delivery into SendGrid’s system is usually within seconds. There’s nothing to tune in your code for this.
- **Inbox delivery** – Once SendGrid has the message, delivery to the user’s inbox (Gmail, Outlook, etc.) is controlled by the recipient’s mail provider. Delays of a few seconds to several minutes are normal and cannot be fixed in your app. You can check delivery status in the [SendGrid Activity feed](https://app.sendgrid.com/activity).

## Summary

| Levers | Action |
|--------|--------|
| **Queue priority** | Run worker with `--queue=emails,default` |
| **Worker sleep** | Use `--sleep=1` or `--sleep=0` |
| **Optional no-queue** | Set `MAIL_WELCOME_SYNC=true` for immediate send |
| **SendGrid / inbox** | Monitor in SendGrid; delays there are provider-dependent |

See also: [PRODUCTION_EMAIL_SETUP.md](PRODUCTION_EMAIL_SETUP.md), [SENDGRID_SETUP.md](SENDGRID_SETUP.md).
