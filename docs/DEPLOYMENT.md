# Deployment Guide

Two supported targets:

- **A. Ubuntu server (VPS / dedicated)**: recommended. Nginx + PHP-FPM + MySQL + Redis + Supervisor + Reverb.
- **B. cPanel shared hosting**: works without Redis, Supervisor or WebSockets (cron-driven queue, no live updates).

Complete [INSTALL.md](INSTALL.md) steps 2–5 on the target first.

---

## A. Ubuntu 24.04 LTS

### A.1 Packages

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install nginx mysql-server redis-server supervisor unzip git \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl \
  php8.3-zip php8.3-gd php8.3-intl php8.3-redis php8.3-sqlite3
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

### A.2 Application

```bash
sudo mkdir -p /var/www/garage-erp && sudo chown $USER:www-data /var/www/garage-erp
cd /var/www/garage-erp      # copy the project here
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# edit .env: APP_URL, DB_*, MAIL_*, REVERB_*, CACHE_STORE=redis, QUEUE_CONNECTION=redis
php artisan erp:install --admin=owner@example.om --password='Strong-Pass-123'
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache

sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

### A.3 Nginx

`/etc/nginx/sites-available/garage-erp`:

```nginx
server {
    listen 80;
    server_name erp.example.om;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name erp.example.om;
    root /var/www/garage-erp/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/erp.example.om/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/erp.example.om/privkey.pem;

    client_max_body_size 25M;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    # WebSockets (Laravel Reverb) proxied on the same domain
    location /app {
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 60s;
        proxy_pass http://127.0.0.1:8080;
    }

    location ~ /\.(?!well-known) { deny all; }
    location = /sw.js { add_header Cache-Control "no-cache"; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/garage-erp /etc/nginx/sites-enabled/
sudo apt -y install certbot python3-certbot-nginx && sudo certbot --nginx -d erp.example.om
sudo nginx -t && sudo systemctl reload nginx
```

`.env` for Reverb behind Nginx: `REVERB_HOST=erp.example.om`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, `REVERB_SERVER_HOST=127.0.0.1`, `REVERB_SERVER_PORT=8080`.

Set in `/etc/php/8.3/fpm/php.ini`: `upload_max_filesize = 20M`, `post_max_size = 25M`, `memory_limit = 256M`, `date.timezone = Asia/Muscat`, `expose_php = Off`.

### A.4 Supervisor (queue worker + Reverb)

`/etc/supervisor/conf.d/garage-erp.conf`:

```ini
[program:garage-erp-queue]
command=php /var/www/garage-erp/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/var/www/garage-erp/storage/logs/queue.log

[program:garage-erp-reverb]
command=php /var/www/garage-erp/artisan reverb:start --host=127.0.0.1 --port=8080
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/www/garage-erp/storage/logs/reverb.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

### A.5 Scheduler (cron)

```bash
sudo crontab -u www-data -e
* * * * * cd /var/www/garage-erp && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs (`routes/console.php`, times in Asia/Muscat):

| Job | When | What |
|---|---|---|
| `erp:daily` | 07:00 daily | periodic service & appointment reminders, appointment no-shows, quotation and warranty expiry, low-stock / reorder alerts, employee document expiries (ID, visa, passport), equipment maintenance & calibration alerts, subscription status |
| `erp:backup` | 02:00 daily | database + uploaded files zip, retention `BACKUP_KEEP_DAYS` |
| `erp:depreciation` | 1st of month 03:00 | fixed asset depreciation for the previous month |
| prunes | daily / weekly | expired API tokens, failed jobs, idempotency keys |

### A.6 Security checklist

- `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
- Strong database password; MySQL bound to `127.0.0.1`; Redis bound to localhost with `requirepass`
- `ufw allow 22,80,443/tcp && ufw enable` (port 8080 stays internal)
- Change the demo / install passwords; enable 2FA for administrators (Profile → Two-factor authentication)
- Copy backups off the server (see [BACKUP_RESTORE.md](BACKUP_RESTORE.md))
- Optional: `SIEM_ENABLED=true` to forward login, permission and posting events to your SIEM over syslog

### A.7 Updating

```bash
cd /var/www/garage-erp
php artisan down
php artisan erp:backup
# replace the code with the new release
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan erp:install        # re-syncs permissions/roles; safe to repeat
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart all
php artisan up
```

---

## B. cPanel shared hosting

1. **PHP version**: cPanel → *Select PHP Version* → 8.3; enable `bcmath`, `intl`, `gd`, `zip`, `pdo_mysql`, `fileinfo`.
2. **Database**: *MySQL Databases* → create database + user, grant ALL.
3. **Upload** the project (with `vendor/`, run `composer install --no-dev` locally first if SSH is not available) to `~/garage-erp` (outside `public_html`).
4. **Web root**: point the (sub)domain document root to `~/garage-erp/public`. If that is not possible, copy the contents of `public/` into `public_html/` and edit `public_html/index.php` paths to `__DIR__.'/../garage-erp/vendor/autoload.php'` and `__DIR__.'/../garage-erp/bootstrap/app.php'`.
5. **.env**: `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `BROADCAST_CONNECTION=log` (no WebSockets on shared hosting; Kanban refreshes on reload).
6. **Install** via *Terminal* (or SSH): `php artisan erp:install --admin=... --password=...` and `php artisan storage:link`.
7. **Cron jobs** (cPanel → *Cron Jobs*):

```
* * * * * cd ~/garage-erp && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd ~/garage-erp && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

8. **Backups**: if `mysqldump` is not allowed, use cPanel *Backup* for the database and download `storage/app` periodically.

---

## Scaling notes

- Run PHP-FPM, queue workers and Reverb on separate nodes behind a load balancer; share sessions/cache via Redis and files via shared storage (or S3-compatible disk).
- Enable `REVERB_SCALING_ENABLED=true` (Redis pub/sub) when running several Reverb nodes.
- MySQL: keep InnoDB buffer pool at ~60–70% of RAM on a dedicated DB server; all tenant tables are indexed on `company_id`.
