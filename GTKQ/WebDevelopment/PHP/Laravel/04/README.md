# Laravel — Deploying as a Container Image

[03](../03/README.md) put the app on a server by installing PHP, nginx and MySQL onto it and
pulling code with git. This lesson does the same job differently: the app is **built into an
image**, pushed to a registry, and the server only pulls and runs it.

The architecture is unchanged — nginx in front, PHP-FPM behind, workers and a scheduler
alongside. What changes is that the server holds no source code, no Composer, and no Node, and
a deploy is "run this exact image" rather than "run these steps and hope they produce the same
result as last time."

> **On credentials:** every place below that needs a login is marked, with a note on where the
> value comes from. The sample files use `<ANGLE_BRACKET>` placeholders. Nothing in this
> repository is a real secret, and none of these belong in git.

## Is This Worth It?

Honestly, for a single small app on one server, lesson 03 is simpler and perfectly respectable.
The container approach earns its keep when:

- more than one server runs the app, and they must be identical
- you want rollback to be "run the previous tag" rather than "un-run the deploy script"
- CI needs to test the *actual* artefact that will run in production
- the team already runs containers for everything else

It costs you: a build pipeline, a registry, and the discipline described below about state. If
none of the above apply, staying on 03 is a legitimate engineering decision rather than a
shortcut.

## The Rule That Explains Everything Else

**The container filesystem is destroyed on every deploy.**

Almost every containerisation mistake is some version of forgetting that. In lesson 03 the
server persisted between deploys, so writing to disk was fine. Here it is not:

| State | On a VPS (03) | In a container (04) |
|---|---|---|
| Logs | `storage/logs/laravel.log` | **stdout** → collected by the platform |
| Sessions | Files or Redis | **Redis** — files would log everyone out each deploy |
| Cache | Files or Redis | **Redis** — a file cache would be empty after each release |
| Uploads | `storage/app/public` | **S3** or compatible — local files vanish |
| `.env` | A file on the server | Environment variables injected at run time |
| Config cache | Built at deploy | Built at **container start**, not image build |

That last row is subtle and worth stating plainly: `php artisan config:cache` freezes the result
of every `env()` call into a file. Run it during the image build and you bake in the build
machine's environment — which is empty. So it runs in `entrypoint.sh` instead, when the real
environment variables are present. This is also what lets one image serve both staging and
production.

## Files

| File | Goes where |
|---|---|
| `Dockerfile` | Project root — builds both the `app` and `web` images |
| `nginx.conf` | Project root — baked into the `web` image |
| `opcache.ini` | Project root — baked into the `app` image |
| `entrypoint.sh` | Project root — runs at container start |
| `.dockerignore` | Project root — reuse the one from [02](../02/.dockerignore) |
| `docker-compose.prod.yml` | **The server**, e.g. `/opt/myapp/` |
| `env.production.example` | The server, renamed to `.env` and filled in |
| `github-actions-deploy.yml` | The project's `.github/workflows/deploy.yml` |

## 1. The Build

One Dockerfile, four stages, two output images:

```
composer:2  ──►  vendor    (composer install --no-dev, optimised autoloader)
                    │
node:22     ──►  assets    (npm ci && npm run build)
                    │
                    ├──►  app  =  php:8.4-fpm  + extensions + code + vendor + built assets
                    └──►  web  =  nginx        + public/ only
```

Two images rather than one because they have genuinely different jobs, and because the `web`
image then contains **no application code, no vendor, and no `.env`** — there is nothing above
the document root in it to leak.

The staging is also what keeps builds fast: dependency manifests are copied before source code,
so editing a controller does not re-run `composer install`.

```bash
# From the project root
docker build --target app -t ghcr.io/<YOUR_GITHUB_USERNAME>/myapp:1.0.0     .
docker build --target web -t ghcr.io/<YOUR_GITHUB_USERNAME>/myapp-web:1.0.0 .
```

### On tags

Use a git SHA or a release number. **Never `latest`** — it makes the running version unknowable,
and rollback impossible, because you cannot name the thing you want to go back to.

## 2. The Registry

An image has to be stored somewhere the server can pull from.

> **Credentials needed here.** `docker login` writes a token to `~/.docker/config.json`.
> Run these yourself — they are interactive.

**GitHub Container Registry** (free for public images, and already tied to your repo):

```bash
# Create a token at: GitHub → Settings → Developer settings →
#   Personal access tokens → Tokens (classic), scope: write:packages
echo "<YOUR_GITHUB_PAT>" | docker login ghcr.io -u <YOUR_GITHUB_USERNAME> --password-stdin
```

**Docker Hub:**

```bash
# Token from: hub.docker.com → Account Settings → Personal access tokens
# Use a token, not your account password.
echo "<YOUR_DOCKERHUB_TOKEN>" | docker login -u <YOUR_DOCKERHUB_USERNAME> --password-stdin
```

Then:

```bash
docker push ghcr.io/<YOUR_GITHUB_USERNAME>/myapp:1.0.0
docker push ghcr.io/<YOUR_GITHUB_USERNAME>/myapp-web:1.0.0
```

The server needs to log in too, once, so `docker compose pull` can authenticate — unless the
images are public, in which case pulls need no credentials at all.

## 3. The Server

A far shorter setup than lesson 03. Docker is the only requirement:

```bash
# On the server
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER      # log out and back in for this to apply
```

Then:

```bash
sudo mkdir -p /opt/myapp && sudo chown $USER /opt/myapp
cd /opt/myapp

# Copy docker-compose.prod.yml and env.production.example up from your machine
scp docker-compose.prod.yml env.production.example <YOUR_USER>@<YOUR_SERVER>:/opt/myapp/

mv env.production.example .env
nano .env                          # fill in every <ANGLE_BRACKET> value
chmod 600 .env                     # contains APP_KEY and the DB password
```

`.env` on the server is the one piece of state that is **not** in an image and not in git. It is
created once, by hand, and backed up somewhere safe — a password manager or a secrets service.

> **`APP_KEY` warning.** Generate it once with `php artisan key:generate --show` and keep it.
> Losing it makes every encrypted column and signed cookie permanently unreadable. Regenerating
> it is not a fix; it is data loss.

Then start it:

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml ps
```

### HTTPS

The compose stack exposes port 80 only. Certificates deliberately do not go inside the image —
that would make the image environment-specific. Terminate TLS in front of it:

- **Caddy** on the same host, reverse-proxying to `localhost:80` — obtains and renews
  certificates automatically, the least work of the three
- **Traefik** as another compose service, if you like label-driven config
- **A cloud load balancer** (ALB, DigitalOcean LB) with a managed certificate

Certbot-on-the-host from [03](../03/README.md#6-https) also works if nginx is already installed
there as a front proxy.

## 4. Migrations

They are **not** in the entrypoint, and that is deliberate. With one container it would work
fine; with two or more starting together they race, running the same migration simultaneously
and leaving the schema half-applied. Every container restart would attempt them, too.

So migrations are a deploy step — one command, run once, before the new containers start:

```bash
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
```

This also means **migrations must be backwards-compatible** during a rolling deploy, since old
and new code briefly run against the same schema. Dropping a column the old code still selects
takes the site down for the length of the rollout. The standard workaround is to split it across
two releases: stop using the column, ship, then drop it.

## 5. Workers and the Scheduler

In lesson 03 these were Supervisor and a crontab. Here they are just more containers running the
same image with a different command:

| Lesson 03 | Lesson 04 |
|---|---|
| Supervisor keeps `queue:work` alive | `restart: unless-stopped` on the `queue` service |
| `numprocs=2` | `replicas: 2`, or `--scale queue=3` |
| `stopwaitsecs` | `stop_grace_period: 60s` |
| Crontab running `schedule:run` every minute | A `scheduler` service running `schedule:work` |
| `php artisan queue:restart` after deploy | Not needed — new code means new containers |

> **Exactly one scheduler replica, always.** Two means every scheduled task fires twice.

The `queue:restart` line disappearing is a nice illustration of the model: there is no such thing
as a worker running stale code, because code cannot change inside a running container.

## 6. Deploying Again

Manually:

```bash
cd /opt/myapp
sed -i 's/^IMAGE_TAG=.*/IMAGE_TAG=1.1.0/' .env
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
docker compose -f docker-compose.prod.yml up -d
docker image prune -f              # old images fill the disk otherwise
```

Rollback is the same thing pointed backwards:

```bash
sed -i 's/^IMAGE_TAG=.*/IMAGE_TAG=1.0.0/' .env
docker compose -f docker-compose.prod.yml up -d
```

Which is the real payoff over lesson 03 — nothing is rebuilt or reinstalled, so the previous
version comes back exactly as it was. Note that this rolls back *code*, not *schema*: a
migration that dropped a column is not undone by changing the image tag.

Automatically: `github-actions-deploy.yml` does all of the above on a version tag. Its header
lists the three repository secrets it needs (`SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`) and how
to create them. Generate a **dedicated** SSH key for CI rather than reusing your personal one.

## 7. Where Else These Images Run

Nothing above is compose-specific — the same two images run on:

| Platform | Notes |
|---|---|
| **A single VPS with compose** | What this lesson does. Simplest thing that works |
| **AWS ECS / Fargate** | No servers to manage; task definitions replace the compose file |
| **Google Cloud Run** | Scales to zero; needs the app image to listen on HTTP directly (FrankenPHP suits this better than FPM) |
| **Kubernetes** | Deployments, a Service, a CronJob for the scheduler. Sensible at multi-service scale, heavy below it |

## The FrankenPHP Alternative

There is a newer option worth knowing about. **FrankenPHP** is a Go-based server with PHP
embedded, so nginx and PHP-FPM collapse into a single process — one image instead of two, and
with **Laravel Octane** the framework boots once and stays in memory rather than per request.

```dockerfile
FROM dunglas/frankenphp:php8.4
RUN install-php-extensions pdo_mysql redis intl zip gd opcache
COPY . /app
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0"]
```

Considerably simpler, and faster. The catch is that a persistent worker changes how you must
write code: static properties, singletons and container bindings survive between requests, so
anything holding request-specific state leaks it into the *next* user's request. That is a real
class of bug that FPM's process-per-request model makes impossible.

Worth adopting deliberately, on a codebase you know, rather than as a default.

## Troubleshooting

| Symptom | Cause |
|---|---|
| `File not found` / `Primary script unknown` | `SCRIPT_FILENAME` in nginx does not match the app container's path — both must be `/var/www/html` |
| `502 Bad Gateway` | The `app` container is not running, or crashed at start — `docker compose logs app` |
| Config changes have no effect | Caches are built at container start; a changed `.env` needs `up -d --force-recreate` |
| `env()` returns null | Called outside `config/*.php` while config is cached — read `config()` instead |
| Uploads disappear after deploy | `FILESYSTEM_DISK` still `local` — must be `s3` |
| Users logged out on every deploy | `SESSION_DRIVER` still `file` |
| Logs empty in `docker compose logs` | `LOG_CHANNEL` still `stack`, writing to a file inside the container |
| `Permission denied` on `storage/` | A mounted volume shadowing it; the entrypoint recreates the directories but cannot fix a bad mount |
| Code edits inside the container do nothing | Correct and intended — `opcache.validate_timestamps=0`. Build a new image |
| Disk full on the server | Old images accumulating — `docker image prune -f` after each deploy |
| Scheduled tasks running twice | More than one `scheduler` replica |

```bash
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml exec app php artisan about
docker compose -f docker-compose.prod.yml exec app sh
```

## Checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `APP_KEY` generated once, backed up outside the server
- [ ] `.env` on the server only — `chmod 600`, never in git, never in an image layer
- [ ] Images tagged with a SHA or version, never `latest`
- [ ] `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` all `redis`
- [ ] `FILESYSTEM_DISK=s3` — nothing user-uploaded on the container filesystem
- [ ] `LOG_CHANNEL=stderr`
- [ ] Database managed and external, with automated backups and a tested restore
- [ ] Migrations as a separate one-shot step, and backwards-compatible
- [ ] Exactly one scheduler replica
- [ ] HTTPS terminated in front of the stack
- [ ] `docker image prune` in the deploy, or the disk fills
- [ ] Error tracking configured — with `APP_DEBUG=false` nothing else will tell you

## Key Information

Comparing this with [03](../03/README.md) is the point of having both. The same architecture is
present in each; what differs is where state is allowed to live. The VPS lets you write to disk,
and so the app quietly accumulates dependencies on that disk. The container forbids it, which
forces sessions, cache, uploads and logs out into services that were always the more robust place
for them.

That is why apps deployed this way tend to move between servers easily — not because containers
are magic, but because the constraint made you externalise the state up front.
