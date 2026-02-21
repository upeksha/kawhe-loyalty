# Fix "Not Secure" with Cloudflare

After changing DNS in Cloudflare, the site can show **"Not secure"** because the connection is HTTP or the certificate is invalid. Here’s how to fix it.

---

## Option A: Quick fix – Cloudflare Flexible SSL (no cert on server)

Use this only as a **temporary** fix. Traffic is encrypted between the visitor and Cloudflare, but **Cloudflare → your server** is still HTTP.

1. **Cloudflare Dashboard** → your domain (e.g. **kawhe.shop**) → **SSL/TLS**.
2. Set **SSL/TLS encryption mode** to **Flexible**.
   - Visitor ↔ Cloudflare: **HTTPS**
   - Cloudflare ↔ your server: **HTTP**
3. Ensure the DNS record for **app.kawhe.shop** (or the host you’re using) has the **orange cloud** (Proxied) turned **on**. If it’s grey (DNS only), the browser talks directly to your server and will still see "Not secure" if the server has no SSL.

**Limitation:** With Flexible, the segment from Cloudflare to your server is not encrypted. Prefer Option B for production.

---

## Option B: Proper fix – HTTPS all the way (recommended)

Encrypt both visitor↔Cloudflare and Cloudflare↔server using a certificate on your server (e.g. Let’s Encrypt).

### 1. Cloudflare: proxy and SSL mode

1. **DNS** → record for **app.kawhe.shop** (A or CNAME):
   - **Proxy status**: **Proxied** (orange cloud). If it’s grey, turn the proxy on.
2. **SSL/TLS** → **Overview**:
   - Set encryption mode to **Full** or **Full (strict)**.
   - **Full**: Cloudflare uses HTTPS to your server; your server can use a self-signed cert.
   - **Full (strict)**: Your server must have a valid certificate (e.g. Let’s Encrypt). Use this when the server has a real cert.

### 2. Server (DigitalOcean): install HTTPS certificate

On the droplet that serves the Laravel app (Ubuntu/Debian example):

**Install Certbot and get a certificate**

```bash
sudo apt update
sudo apt install certbot

# If you use Nginx (Certbot will configure it):
sudo apt install python3-certbot-nginx
sudo certbot --nginx -d app.kawhe.shop

# If you use Apache:
# sudo apt install python3-certbot-apache
# sudo certbot --apache -d app.kawhe.shop
```

- Use the same domain you use in the browser (e.g. **app.kawhe.shop**).
- Certbot will request a Let’s Encrypt certificate and, with `--nginx` or `--apache`, update the vhost to use HTTPS and redirect HTTP → HTTPS.

**If Nginx is not installed yet**, you can still get a certificate and then configure Nginx manually:

```bash
sudo certbot certonly --standalone -d app.kawhe.shop
```

(Stop Nginx/Apache first if they’re already running, then start them again after configuring the vhost to use the cert. Certificates are usually in `/etc/letsencrypt/live/app.kawhe.shop/`.)

### 3. Nginx example (HTTPS vhost)

After Certbot, or if you configure by hand, the server block should look like this in spirit:

```nginx
server {
    listen 443 ssl http2;
    server_name app.kawhe.shop;

    ssl_certificate     /etc/letsencrypt/live/app.kawhe.shop/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.kawhe.shop/privkey.pem;

    root /var/www/kawhe/public;   # adjust to your Laravel public path
    index index.php;
    # ... rest of Laravel config (try_files, php-fpm, etc.)
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name app.kawhe.shop;
    return 301 https://$server_name$request_uri;
}
```

Reload Nginx after changes:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 4. Auto-renewal (Let’s Encrypt)

Certbot usually adds a cron job. Test renewal with:

```bash
sudo certbot renew --dry-run
```

### 5. Cloudflare after the server has HTTPS

- Set SSL/TLS mode to **Full** or **Full (strict)** (Full (strict) once the server has a valid cert).
- Visit **https://app.kawhe.shop** – the browser should show **Secure** (lock icon).

---

## Checklist

- [ ] DNS record for **app.kawhe.shop** is **Proxied** (orange cloud) in Cloudflare.
- [ ] SSL/TLS mode is **Full** or **Full (strict)** (not Off, and Flexible only as a temporary measure).
- [ ] Server has a valid certificate (e.g. `certbot --nginx -d app.kawhe.shop`) and Nginx/Apache is configured for HTTPS.
- [ ] HTTP redirects to HTTPS on the server (or via Cloudflare “Always Use HTTPS” in **SSL/TLS** → **Edge Certificates**).
- [ ] **APP_URL** in Laravel `.env` is **https://app.kawhe.shop** (no trailing slash).

---

## Still "Not secure"?

- **Mixed content:** If the page is HTTPS but scripts/images load over `http://`, the browser can show "Not secure". Fix by making all URLs in HTML and Laravel use `https://` or relative URLs, and ensure **APP_URL** is `https://...`.
- **Wrong domain:** Certificate must be for the host you’re visiting (e.g. **app.kawhe.shop**). Run `certbot` for that exact name.
- **Direct to IP or bypassing Cloudflare:** If you open `http://YOUR_SERVER_IP` or use "DNS only" (grey cloud), the browser won’t see Cloudflare’s certificate. Use **https://app.kawhe.shop** with the proxy on.
