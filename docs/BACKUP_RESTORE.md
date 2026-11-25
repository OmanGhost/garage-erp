# Backup & Restore

## What is backed up

`php artisan erp:backup` creates `storage/app/backups/backup-YYYYMMDD-HHMMSS.zip` containing:

| Path in zip | Content |
|---|---|
| `db-YYYYMMDD-HHMMSS.sql` | the full MySQL database (all companies) via `mysqldump --single-transaction` |
| `files/private/attachments/…` | uploaded documents, vehicle/inspection photos, scanner reports |
| `files/private/signatures/…` | customer signatures captured at reception and delivery |
| `files/public/…` | logos and public images |

Options:

```bash
php artisan erp:backup              # database + files
php artisan erp:backup --no-files   # database only
```

Backups older than `BACKUP_KEEP_DAYS` (default 14) are deleted after each run.

## Automatic schedule

The scheduler runs `erp:backup` every day at **02:00 Asia/Muscat**, provided the cron entry from [DEPLOYMENT.md](DEPLOYMENT.md) is installed:

```
* * * * * cd /var/www/garage-erp && php artisan schedule:run >> /dev/null 2>&1
```

Backups can also be created and downloaded from **Administration → Backups** in the web interface. Because an archive contains the **whole database (all companies)**, this page is available only to the platform super admin, or to the Company Admin when the installation hosts a single company (on-premise). Every creation and download is written to the audit log.

## Off-site copies (strongly recommended)

A backup that stays on the same server does not protect against disk loss or ransomware. Copy the archives daily to another location, e.g. with `rclone` to any S3-compatible or SFTP storage:

```bash
# /etc/cron.d/garage-erp-offsite
30 2 * * * www-data rclone copy /var/www/garage-erp/storage/app/backups remote:garage-erp-backups --max-age 48h
```

The archives contain personal and financial data: store them encrypted (e.g. an `rclone crypt` remote) and restrict access.

## Restore

> Restoring **overwrites** the current database and uploaded files. Take a fresh backup first.

```bash
php artisan down
php artisan erp:backup                                       # safety copy of the current state
php artisan erp:restore backup-20261125-020000.zip           # file name in storage/app/backups
# or an absolute path:  php artisan erp:restore /home/backups/backup-20261125-020000.zip
php artisan erp:restore <file> --db-only                     # database only, keep current files
php artisan erp:restore <file> --force                       # no confirmation prompt (scripts)
php artisan optimize:clear
php artisan up
```

After a restore to a **different server**, also copy `.env` (the same `APP_KEY` is required to decrypt stored integration credentials and two-factor secrets), then run `php artisan storage:link`.

## Manual restore (without the command)

```bash
unzip backup-20261125-020000.zip -d /tmp/restore
mysql -u garage_erp -p garage_erp < /tmp/restore/*.sql
rsync -a /tmp/restore/files/private/ storage/app/
rsync -a /tmp/restore/files/public/  storage/app/public/
```

## Test your backups

Once a month, restore the latest backup on a separate machine or database and log in. A backup that has never been restored is not a proven backup.

## Keep `APP_KEY` safe

`APP_KEY` encrypts sessions, integration credentials (SMS, WhatsApp, payment gateway) and users' two-factor secrets. Store a copy of `.env` in your password manager or vault. Without it those encrypted values cannot be recovered (users would need to re-enrol 2FA and integrations would need new credentials).
