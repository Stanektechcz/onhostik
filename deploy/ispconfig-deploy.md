# OnHost.cz — ISPConfig 3 Deployment Guide

> ISPConfig manages Apache/PHP-FPM per-site. Do NOT manually edit files in
> `/etc/apache2/sites-available/` for ISPConfig-managed domains — ISPConfig
> overwrites them. Instead, paste custom directives into the ISPConfig UI.

---

## 1. ISPConfig Website Setup

### 1a. Create the website in ISPConfig

Go to: **Sites → Add Website**

| Field                | Value                          |
|----------------------|-------------------------------|
| Client               | (your client or no client)    |
| Domain               | `onhost.cz`                   |
| Document Root        | `/web`  *(ISPConfig appends this to the site path)* |
| PHP                  | PHP-FPM                       |
| PHP Version          | 8.2                           |
| SSL                  | Let's Encrypt ✓               |
| Force SSL            | ✓                             |
| HTTP/2               | ✓                             |

**Important:** ISPConfig will set the full document root to something like:
`/var/www/clients/client1/web1/web`

The Laravel app must be placed so that its `public/` directory is at that path:
```
/var/www/clients/client1/web1/    ← app root (composer install here)
/var/www/clients/client1/web1/web ← symlink OR copy of public/
```

**Recommended approach:** Place the full Laravel app at:
```
/var/www/onhost/
```
Then in ISPConfig, under **Advanced Options → Custom Document Root**:
```
/var/www/onhost/public
```

### 1b. Create admin subdomain

Go to: **Sites → Add Subdomain** (or Add Website for `admin.onhost.cz`)

| Field                | Value                          |
|----------------------|-------------------------------|
| Domain               | `admin.onhost.cz`             |
| Document Root        | `/var/www/onhost/public`      |
| PHP                  | PHP-FPM 8.2                   |
| SSL                  | Let's Encrypt ✓               |

---

## 2. Custom Apache Directives

In ISPConfig for each site (Options → Apache Directives), paste:

### For onhost.cz:

```apache
# Laravel URL rewriting
<Directory /var/www/onhost/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# Security headers
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"

# Cache static assets
<LocationMatch "^/(front|panel)/(css|js|fonts|img|svg)/.*\.(css|js|woff2?|ttf|eot|svg|png|jpg|ico)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
</LocationMatch>
```

### For admin.onhost.cz:

```apache
<Directory /var/www/onhost/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# Redirect / → /admin
RewriteEngine On
RewriteRule ^/?$ /admin [R=302,L]

Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set Strict-Transport-Security "max-age=31536000"

# Optional: restrict admin access by IP
# <Location /admin>
#     Require ip 1.2.3.4
# </Location>
```

---

## 3. PHP-FPM Pool Configuration

ISPConfig creates a PHP-FPM pool per site. For OnHost, ensure:

- **PHP version:** 8.2
- **Max children:** 10–20 (depending on RAM)
- **Max requests:** 500
- **Process manager:** dynamic

Required PHP extensions (verify in ISPConfig → PHP Versions → Extensions):
```
bcmath, ctype, curl, fileinfo, gd, intl, json, mbstring, 
openssl, pdo_mysql, tokenizer, xml, zip
```

---

## 4. File Permissions

After cloning the repo, run from outside ISPConfig:

```bash
cd /var/www/onhost
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
php8.2 artisan storage:link
```

> ISPConfig's FTP user also needs read access to the app, but should NOT have
> write access outside of storage/ and public/ upload directories.

---

## 5. Database in ISPConfig

Go to: **Sites → Databases → Add Database**

| Field    | Value   |
|----------|---------|
| Database | `onhost` |
| User     | `onhost` |
| Password | (generate strong) |
| Charset  | utf8mb4 |

Copy the generated credentials to your `.env`:
```dotenv
DB_HOST=127.0.0.1
DB_DATABASE=c1onhost   # ISPConfig prefixes with client number
DB_USERNAME=c1onhost
DB_PASSWORD=<generated>
```

---

## 6. SSL / Let's Encrypt

ISPConfig handles Let's Encrypt automatically when you check **SSL** in the website settings. Ensure:

- Domain DNS is pointing to this server's IP before requesting
- Port 80 is accessible from the internet (for ACME challenge)
- After certificate issuance, ISPConfig enables HTTPS automatically

---

## 7. Queue Worker and Cron

These are NOT managed by ISPConfig — set them up at the OS level:

**Supervisor** (queue worker):
```bash
cp /var/www/onhost/deploy/supervisor-queue.conf /etc/supervisor/conf.d/onhost-queue.conf
supervisorctl reread && supervisorctl update
```

**Cron** (Laravel scheduler):
```bash
cp /var/www/onhost/deploy/cron.txt /etc/cron.d/onhost
chmod 644 /etc/cron.d/onhost
```

---

## 8. ISPConfig Backup Integration

ISPConfig's backup system backs up the DocumentRoot but NOT files outside it.
The full app at `/var/www/onhost/` needs a separate backup strategy:

```bash
# Example: daily rsync backup (add to cron)
rsync -az --delete /var/www/onhost/ backup@backup-server:/backups/onhost/
```

---

## 9. Webhook URL Accessibility

For Comgate payments, the webhook URL must be publicly accessible:
```
https://onhost.cz/api/webhooks/comgate
```

Verify with:
```bash
curl -X POST https://onhost.cz/api/webhooks/comgate \
  -d 'transId=TEST&merchant=YOUR_ID' \
  -v
```
Expected response: HTTP 200

---

## 10. Post-Deploy Verification

```bash
# Run the built-in doctor
php8.2 artisan onhost:doctor --production

# Test the health endpoint
curl -s https://onhost.cz/up   # expects 200

# Check logs
tail -f /var/www/onhost/storage/logs/laravel.log
```
