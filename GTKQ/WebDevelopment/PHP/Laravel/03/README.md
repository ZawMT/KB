# Laravel — Deploying to a VPS by Hand

**VPS** = Virtual Private Server: a slice of a physical machine, rented out as though it were a
whole Linux computer of your own. You get root access, a public IP address, and an empty OS —
nothing is installed until you install it. DigitalOcean Droplets, Hetzner Cloud, Linode, Vultr
and EC2 instances are all VPSs.

Worth distinguishing from the alternatives:

| | What you get | Laravel there? |
|---|---|---|
| **Shared hosting** | A folder on someone else's server, via cPanel/FTP | Awkward — usually no SSH, no Composer, no control over the document root |
| **VPS** | A whole Linux box you administer | Yes — this lesson |
| **Managed VPS** (Forge, Ploi) | The same box, provisioned and deployed for you | Yes — these automate this lesson |
| **PaaS** (Laravel Cloud, Fly.io, Render) | Push code, the platform builds and runs it | Yes — no server to administer |

This lesson takes a working Laravel app and puts it on a public server: a plain Ubuntu box,
set up manually, with nginx and PHP-FPM.

Doing it by hand once is worth it. Managed services (Laravel Forge, Ploi, Laravel Cloud) automate
exactly these steps, and it is much easier to use — or debug — those tools after having seen what
they are doing.

> Everything below is run **on the server**, over SSH, unless marked otherwise.
> Substitute your own values for `example.com`, `myapp` and the PHP version throughout.

## What We Are Building

```
        Internet
           │
           │  :80 / :443
    ┌──────▼──────────────────────────────────────┐
    │  nginx            root → /var/www/myapp/public
    │    │                                        │
    │    │  .php requests over a unix socket      │
    │    ▼                                        │
    │  PHP-FPM          runs public/index.php     │
    │    │                                        │
    │    ├──────────► MySQL                       │
    │    └──────────► Redis                       │
    │                                             │
    │  Supervisor  ──►  queue:work (kept alive)   │
    │  cron        ──►  schedule:run (every min)  │
    └─────────────────────────────────────────────┘
```

Two things drive most of the setup:

- **nginx serves `public/`, never the project root.** The project root contains `.env`. Point
  the document root there and anyone can fetch your database password over HTTP. This is the
  single most damaging Laravel misconfiguration.
- **nginx cannot run PHP.** It hands `.php` requests to PHP-FPM, a separate long-running process
  pool, and passes the response back. `php artisan serve` from [01](../01/README.md) has no part
  in production.

## 1. The Server

Any provider works — DigitalOcean, Hetzner, Linode, Vultr, EC2. A 1–2 GB instance is enough to
start. Choose **Ubuntu 24.04 LTS**.

Add your SSH public key during creation if the provider offers it; otherwise copy it up:

```bash
# On your own machine
ssh-copy-id root@203.0.113.10
ssh root@203.0.113.10
```

### A non-root user

Running the app as root is unnecessary risk. Create a deploy user:

```bash
adduser deploy
usermod -aG sudo deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy
```

From here on, log in as `deploy`:

```bash
ssh deploy@203.0.113.10
```

### Lock down SSH

```bash
sudo nano /etc/ssh/sshd_config
```

```
PermitRootLogin no
PasswordAuthentication no
```

```bash
sudo systemctl restart ssh
```

> Confirm you can still open a **new** SSH session before closing the current one. If key auth
> is not working, `PasswordAuthentication no` will lock you out and only the provider's console
> can recover it.

### Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

MySQL and Redis are deliberately not opened — they are reached over localhost only.

## 2. Installing the Stack

```bash
sudo apt update && sudo apt upgrade -y
```

### PHP

Ubuntu's default PHP may be older than your app needs. The `ondrej/php` PPA is the standard
source for current versions:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y php8.4-fpm php8.4-cli \
    php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
    php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath php8.4-redis

php -v
```

> Match the version to your app. Check `require.php` in the project's `composer.json`, and use
> the same major.minor you develop against — deploying onto a different PHP version than you
> tested on is a reliable way to find out which extension you forgot.

### nginx, MySQL, Redis

```bash
sudo apt install -y nginx mysql-server redis-server
sudo systemctl enable --now nginx mysql redis-server
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer -V
```

### Node

Only needed if the app builds frontend assets with Vite. Building on the server is the simple
option; building in CI and shipping `public/build` is the better one for a small server.

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

## 3. The Database

```bash
sudo mysql
```

```sql
CREATE DATABASE myapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'myapp'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON myapp.* TO 'myapp'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

`'myapp'@'localhost'` means this account can only connect from the server itself. Combined with
the firewall, the database is not reachable from the internet at all.

Then the standard hardening prompts:

```bash
sudo mysql_secure_installation
```

## 4. The Code

```bash
sudo mkdir -p /var/www/myapp
sudo chown deploy:deploy /var/www/myapp
git clone git@github.com:you/myapp.git /var/www/myapp
cd /var/www/myapp
```

For a private repository, generate a deploy key on the server and add it to the repo's
**Deploy keys** (read-only) in GitHub:

```bash
ssh-keygen -t ed25519 -C "deploy@myapp"
cat ~/.ssh/id_ed25519.pub
```

### Dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

`--no-dev` skips PHPUnit, Faker and friends. `--optimize-autoloader` builds a full classmap so
PHP is not scanning directories on every request.

### Environment

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

```ini
APP_NAME=MyApp
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=myapp
DB_PASSWORD=a-long-random-password

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

LOG_CHANNEL=stack
LOG_LEVEL=warning
```

**`APP_DEBUG=false` is not optional.** With it true, any uncaught exception renders a stack trace
containing environment variables — database credentials, API keys, everything — to whoever
triggered it.

`.env` is never committed. It is created once on the server and edited in place.

### Migrations

```bash
php artisan migrate --force
```

`--force` skips the "are you sure?" prompt, which cannot be answered in a non-interactive deploy.

### Permissions

PHP-FPM runs as `www-data`, and it needs to write to exactly two places:

```bash
sudo chown -R deploy:www-data /var/www/myapp
sudo find /var/www/myapp -type f -exec chmod 644 {} \;
sudo find /var/www/myapp -type d -exec chmod 755 {} \;
sudo chmod -R ug+rwx /var/www/myapp/storage /var/www/myapp/bootstrap/cache
```

The pattern: `deploy` owns the files (so deploys work without sudo), `www-data` is the group
(so PHP can write logs, sessions, compiled views and caches). Nothing else is web-writable.

Nearly every "500 error on a fresh deploy" is this step.

### Public storage

If the app serves user uploads:

```bash
php artisan storage:link
```

## 5. nginx

Copy `nginx.conf` from this folder to the server as `/etc/nginx/sites-available/myapp`, editing
`server_name`, `root` and the PHP-FPM socket version:

```bash
sudo nano /etc/nginx/sites-available/myapp
sudo ln -s /etc/nginx/sites-available/myapp /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t          # test the config before reloading
sudo systemctl reload nginx
```

`nginx -t` first, always — reloading a broken config takes the site down.

Point your domain's DNS **A record** at the server's IP, then `http://example.com` should serve
the app.

## 6. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d example.com -d www.example.com
```

Certbot edits the nginx config in place to add the certificate and an HTTP→HTTPS redirect, and
installs a systemd timer for renewal. Check it:

```bash
sudo certbot renew --dry-run
```

Once HTTPS is on, set `APP_URL=https://example.com` in `.env` so generated links and assets use
the right scheme.

## 7. Queue Workers

If the app dispatches jobs, something has to run them — and `queue:work` is a long-running
process that must be restarted when it exits. That is Supervisor's job:

```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/myapp-worker.conf   # contents in laravel-worker.conf here
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

```bash
sudo supervisorctl restart myapp-worker:*    # after a deploy
tail -f /var/www/myapp/storage/logs/worker.log
```

Workers load your code into memory at start and keep it there, so **a worker running old code
after a deploy is a classic bug**. `php artisan queue:restart` signals them to exit gracefully
after the current job; Supervisor then restarts them with the new code. The deploy script does
this.

## 8. The Scheduler

Laravel's scheduler needs exactly one cron entry, running every minute:

```bash
crontab -e
```

```cron
* * * * * cd /var/www/myapp && php artisan schedule:run >> /dev/null 2>&1
```

Everything else is defined in `routes/console.php` inside the app. This one line never changes.

## 9. Deploying Again

Copy `deploy.sh` from this folder to `/var/www/myapp/deploy.sh`:

```bash
chmod +x deploy.sh
./deploy.sh
```

The sequence it runs, and why each step matters:

| Step | Why |
|------|-----|
| `artisan down` | Maintenance page while files are inconsistent mid-deploy |
| `git pull` | New code |
| `composer install --no-dev --optimize-autoloader` | New/changed dependencies |
| `npm ci && npm run build` | Rebuild frontend assets |
| `artisan migrate --force` | Apply schema changes |
| `artisan config:cache` | Merge all config into one cached file |
| `artisan route:cache` | Precompile the route table |
| `artisan view:cache` | Precompile Blade templates |
| `systemctl reload php8.4-fpm` | Clear OPcache so PHP sees the new files |
| `artisan queue:restart` | Workers pick up the new code |
| `artisan up` | Back online |

The caching commands are a real speedup, but they have a sharp edge: **once config is cached,
`env()` returns null outside of config files.** If a class calls `env('SOME_KEY')` directly, it
silently breaks in production and works fine locally. The rule is to read `env()` only inside
`config/*.php`, and use `config('...')` everywhere else.

`config:clear` undoes it while debugging.

## 10. Zero-Downtime (the next step)

The script above has a gap — a few seconds where the site is in maintenance mode. The standard
fix is a releases-and-symlink layout:

```
/var/www/myapp/
├── releases/
│   ├── 20260805103000/
│   └── 20260805141500/     ← built here, fully, before any switch
├── shared/
│   ├── .env                → symlinked into each release
│   └── storage/            → symlinked into each release
└── current → releases/20260805141500
```

nginx's root points at `current/public`. A deploy builds a new release directory completely, then
atomically repoints the `current` symlink. Nothing is ever half-updated, and rollback is
repointing the symlink at the previous release.

Writing this by hand is fiddly. **Deployer** (`composer require deployer/deployer --dev`)
implements it as a PHP-based deploy tool, and **Envoyer** is Laravel's hosted equivalent.

## Troubleshooting

| Symptom | Where to look |
|---------|---------------|
| Blank white page | `storage/logs/laravel.log`, then `/var/log/nginx/error.log` |
| `500` right after deploy | Permissions on `storage/` and `bootstrap/cache/` |
| `403 Forbidden` | `root` is missing `/public`, or the directory is not traversable |
| `404` on every route except `/` | The `try_files ... /index.php?$query_string;` line is missing |
| `502 Bad Gateway` | PHP-FPM is down, or the socket path in nginx has the wrong PHP version |
| Old code still served | OPcache — reload `php8.4-fpm`; and `queue:restart` for workers |
| Config change has no effect | Config is cached — run `php artisan config:cache` again |
| `env()` returns null | Called outside a config file while config is cached |
| `419 Page Expired` on forms | Session driver misconfigured, or `APP_URL`/HTTPS mismatch |
| Assets 404 | `npm run build` not run, or `APP_URL` still `http://` behind HTTPS |

Useful commands:

```bash
tail -f /var/www/myapp/storage/logs/laravel.log
sudo tail -f /var/log/nginx/error.log
sudo systemctl status php8.4-fpm nginx mysql redis-server
php artisan about                # environment, drivers, cache status at a glance
php artisan route:list
```

## Production Checklist

- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] `APP_KEY` generated and backed up — losing it makes encrypted data unrecoverable
- [ ] nginx `root` ends in `/public`
- [ ] HTTPS with auto-renewal verified via `certbot renew --dry-run`
- [ ] `ufw` enabled; MySQL and Redis bound to localhost only
- [ ] SSH: key-only, root login disabled
- [ ] `storage/` and `bootstrap/cache/` writable by `www-data`; nothing else is
- [ ] Config, route and view caches built by the deploy script
- [ ] Queue workers under Supervisor, restarted on deploy
- [ ] Scheduler cron entry present
- [ ] **Automated database backups** — off the server, restore tested at least once
- [ ] Error tracking (Sentry, Flare) and uptime monitoring

## Key Information

Nothing here is Laravel-specific except the artisan commands — the nginx + PHP-FPM + Supervisor +
cron shape is how PHP applications have been deployed for years, and Symfony, WordPress or a
hand-rolled app all sit in the same layout.

Having done it manually, the value of Forge or Ploi is clear: they run these steps for you, on
your own VPS, and add deploy-on-push and certificate management. Nothing about the resulting
server is different — which is why the debugging knowledge above still applies when it breaks.
