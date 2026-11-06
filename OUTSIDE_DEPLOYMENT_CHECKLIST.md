# Server and deployment checklist

This repository is the application code. Complete the following server-side work before deploying it. Do not paste credentials into Git, tickets, or chat.

## Urgent security actions

1. Rotate every credential that has ever appeared in a committed `.env` file: application key, database password, SMTP password, JWT secret, Reverb credentials, Square token, Stripe secrets and webhook secret, Brevo credentials, Setmore token, OAuth secrets, and Firebase service-account key.
2. Remove `.env`, old environment backups, FTP state files, and log files from the Git index. Keep the real `.env` on the server only, outside the repository when the host permits it.
3. Change the hosting panel/SSH/FTP passwords and enable MFA. Review hosting users, scheduled tasks, SSH keys, and deployment keys for unknown entries.

## PHP and web server

1. Upgrade to a supported PHP 8.2 or 8.3 release. Confirm these extensions are enabled: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd` or `imagick`, `intl`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, and `zip`.
2. Point the site document root to the repository's `public/` directory. Do not expose the repository root. This prevents direct requests for `.env`, `storage`, source code, and `vendor` files if rewrite rules fail.
3. Enable HTTPS and redirect HTTP to HTTPS. Set the production environment values: `APP_ENV=production`, `APP_DEBUG=false`, and the canonical `APP_URL`.
4. Give the web-service user write access only to `storage/` and `bootstrap/cache/`. Do not grant recursive write access to the whole project.

## Deploy safely

1. Back up the production database and the complete `public/uploads/` directory before deployment.
2. Upload/checkout the code outside the public web root. Restore `public/uploads/` from the server backup; uploads are application data and are intentionally ignored by Git.
3. Create the server `.env` from `.env.example`, enter newly rotated values, then run:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader
   php artisan migrate --force
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. If any files use Laravel's public storage disk, run `php artisan storage:link`. The current mobile-app images are served from `public/uploads/`, so confirm that directory and its files were restored and are readable by the web server.
5. Build frontend assets on the deployment machine or in CI with `npm ci` followed by `npm run build`, then deploy `public/build/`.

## Background services

1. Configure one cron entry to run every minute:

   ```sh
   * * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
   ```

2. Run a supervised queue worker if `QUEUE_CONNECTION=database` is used:

   ```sh
   php artisan queue:work --sleep=3 --tries=3 --max-time=3600
   ```

3. Configure Reverb separately if live notifications are required; use TLS in production and do not expose an unauthenticated websocket port publicly.

## Verification after deployment

1. Confirm `/api/categories`, `/api/subcategories`, banners, products, selling points, and recommended services return direct image URLs that load in a browser.
2. Test an order in the payment provider's sandbox before enabling live payments.
3. Test the giveaway scheduling flow with a non-production recipient and confirm the intended queue/scheduler jobs run once only.
4. Review `storage/logs/laravel.log`, web-server error logs, and queue logs after deployment. Set up backups and uptime/error monitoring.

## Git remote safety

The remote `main` branch currently diverges from this working branch. Its only remote-only commit contains no detected malware, but it restores secret-bearing environment files and removes the locally added giveaway-pool code and images. Do not pull or merge it as-is. Reconcile it deliberately after the secrets are rotated.
