# Laravel — Running with Docker (Local Development)

In [01](../01/README.md), PHP, Composer and the database were installed directly on the machine.
That works, but it ties the project to whatever PHP version happens to be installed, and every
new machine needs the same setup repeated.

Docker solves that by putting PHP, MySQL and Redis into containers, so the only thing installed
locally is Docker itself.

There are two ways to do this in Laravel, and this lesson covers both:

1. **Laravel Sail** — the official, one-command way.
2. **A hand-written `docker-compose.yml`** — the same thing, spelled out, so it is clear what
   Sail is generating on your behalf.

> Both are **development** setups. Neither is how a Laravel app is served in production —
> that is [03](../03/README.md).

## 1. Laravel Sail

### Prerequisites

Docker Desktop installed and running. Nothing else — not even PHP.

### Adding Sail to a project

If the project was created with the Laravel installer, Sail may already be there. Otherwise:

```bash
composer require laravel/sail --dev
php artisan sail:install
```

It asks which services to include:

```
Which services would you like to install?
 ◯ mysql     ◯ pgsql    ◯ mariadb
 ◯ redis     ◯ memcached
 ◯ meilisearch  ◯ typesense
 ◯ minio     ◯ mailpit
 ◯ selenium  ◯ soketi
```

Pick `mysql`, `redis` and `mailpit` for a typical setup.

This writes a `compose.yaml` into the project root and updates `.env` so `DB_HOST` points at the
container name rather than `127.0.0.1`.

> Current Sail publishes **`compose.yaml`**, not `docker-compose.yml` — the Compose Spec made the
> shorter name canonical and Docker looks for it first. Older tutorials naming the long form are
> describing the legacy filename. The hand-written file below uses `docker-compose.yml` because
> both names still work; pick one and stay with it.

### Running

```bash
./vendor/bin/sail up -d      # start, detached
./vendor/bin/sail down       # stop
```

The app is at `http://localhost` (port 80, not 8000).

Because PHP now lives inside a container, every PHP command has to run *in* that container.
That is all `sail` really is — a wrapper that prefixes commands with `docker compose exec`:

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail composer require some/package
./vendor/bin/sail npm run dev
./vendor/bin/sail mysql              # a MySQL shell inside the db container
./vendor/bin/sail shell              # a bash shell inside the app container
```

Typing `./vendor/bin/sail` gets old quickly, so most people add a shell alias:

```bash
# in ~/.zshrc
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Then it is just `sail up -d`, `sail artisan migrate`, and so on.

## 2. The Hand-Written Equivalent

Sail is convenient but opaque. The files in this folder do the same job explicitly.

### Files

| File | Purpose |
|------|---------|
| `Dockerfile` | Builds the PHP image — extensions, Composer, Node |
| `docker-compose.yml` | Defines the app, MySQL, Redis and Mailpit services |
| `.dockerignore` | Keeps `vendor/`, `node_modules/` and `.env` out of the build context |

Copy all three into the **root of your Laravel project** (next to `artisan` and `composer.json`),
not into this lesson folder.

### What `.dockerignore` Does

It filters the **build context** — the snapshot of files your machine hands to the Docker daemon
when `docker build` runs. Anything listed never leaves your machine, so a `COPY . .` in the
Dockerfile cannot pick it up. It is the `.gitignore` of image building, and it serves two
purposes: keeping the context small, and keeping secrets out.

Size first. `vendor/` and `node_modules/` are hundreds of megabytes that would be uploaded to the
daemon on every build only to be thrown away — the container reinstalls them itself, because host
copies may hold binaries compiled for a different OS or PHP version.

Then the part that matters most:

```
# Secrets — the running container gets these via the bind mount and Compose,
# never baked into an image layer.
.env
.env.*
!.env.example
```

**`.env` holds `APP_KEY`, `DB_PASSWORD` and mail credentials.** Excluding it is not tidiness, it
is the difference between a secret living on one machine and a secret shipped inside an artefact.

The reason it cannot be undone later is that **image layers are immutable and additive**. Copying
`.env` in one layer and deleting it in a later `RUN rm` does not remove it — the file remains in
the earlier layer, readable with `docker history` or by unpacking a `docker save` archive. Push
that image to a registry and the credential travels with it, to everyone who can pull.

**`.env.*` sweeps up the variants** — `.env.local`, `.env.production`, `.env.testing`, and the
`.env.backup` somebody made before an edit. Deliberately broad, because the dangerous file is
always the one nobody remembers creating.

**`!.env.example` puts one file back.** `!` negates an earlier pattern, and it is needed here
because `.env.*` matches `.env.example` as well. That file is worth keeping: it lists every
variable name with placeholder values and no real credentials, so it documents what the
application expects. The negation must appear *after* the rule that excluded it — reversed, it
does nothing.

### So How Does the Container Get `.env`?

Through two paths, neither of which is the image:

**The bind mount.** `./:/var/www/html` maps the project folder into the container when it
*starts*. `.env` is visible inside because the host filesystem is mapped in live — not because
anything was copied at build time.

**Compose.** It reads `.env` from the host to resolve `${DB_PASSWORD}`-style substitutions in
`docker-compose.yml` and to fill `environment:` entries, injecting them as environment variables
into the running process.

The principle underneath: **build time bakes and ships, run time injects per environment.** That
separation is what lets one identical image run in development, staging and production with
different credentials in each — and it is why a secret belongs in neither the image nor git.

> The `Dockerfile` in this folder has no `COPY . .` — all code arrives through the bind mount, so
> the `.env` rules here are defensive rather than load-bearing, and the live benefit is context
> size. They become essential the moment an image copies the source in, which is exactly what a
> production build does.

### Configuring `.env`

Docker Compose automatically reads a `.env` file sitting next to `docker-compose.yml` — and in a
Laravel project that is Laravel's *own* `.env`. So one file configures both. This is exactly the
trick Sail uses.

Change these lines in the project's `.env`:

```ini
DB_CONNECTION=mysql
DB_HOST=mysql              # the service name, not 127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=password

REDIS_HOST=redis           # the service name
REDIS_PORT=6379

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

The single most common mistake here is leaving `DB_HOST=127.0.0.1`. Inside the app container,
`127.0.0.1` means *that container*, not the host and not the database. Containers reach each
other by **service name** — Compose runs a DNS server that resolves `mysql` and `redis`.

### Running

```bash
docker compose build         # first time, or after editing the Dockerfile
docker compose up -d
docker compose logs -f app   # follow the app's output
docker compose down          # stop; add -v to also delete the database volume
```

The app is at `http://localhost:8000`, and Mailpit's inbox at `http://localhost:8025`.

### Running commands inside the container

```bash
docker compose exec app php artisan migrate
docker compose exec app composer install
docker compose exec app npm run dev
docker compose exec app bash            # interactive shell
docker compose exec mysql mysql -ularavel -ppassword laravel
```

## How It Works

```
                    ┌──────────────────────────────────┐
   localhost:8000 ──┤ app      php artisan serve       │
                    │          (bind mount: ./ → /var/www/html)
                    └───────┬──────────────┬───────────┘
                            │              │
                    ┌───────▼──────┐  ┌────▼─────────┐
                    │ mysql        │  │ redis        │
                    │ volume:      │  │ volume:      │
                    │ mysql_data   │  │ redis_data   │
                    └──────────────┘  └──────────────┘
```

Three details worth understanding:

**The bind mount.** `./:/var/www/html` maps the project folder on your machine into the
container. Edit a file in your editor and the container sees it instantly — no rebuild. This is
why development containers feel like local development.

**The named volumes.** `mysql_data` and `redis_data` are managed by Docker and survive
`docker compose down`. Without them, every restart would give an empty database. `docker compose
down -v` deletes them, which is the deliberate way to start clean.

**The user ID.** The Dockerfile creates a user with UID 1000 to match the typical host user.
Without that, files created inside the container (`artisan make:controller`, for example) would
be owned by root on your machine and awkward to edit.

## Why `artisan serve` Here

The `app` service runs `php artisan serve`, the same development server as in [01](../01/README.md).
Sail does the same thing. It is single-threaded and slow, but for local work that does not matter.

Production replaces it with nginx and PHP-FPM, which is what [03](../03/README.md) sets up.
Keeping that out of this lesson is deliberate — mixing a dev container and a production web
server stack into one compose file is where these setups usually become confusing.

## Common Problems

| Symptom | Cause |
|---------|-------|
| `SQLSTATE[HY000] [2002] Connection refused` | `DB_HOST` still `127.0.0.1` — must be `mysql` |
| Connection refused on the *first* `up` | MySQL takes ~20s to initialise; the healthcheck makes the app wait, but migrations run manually may still be early |
| `Port 3306 already allocated` | A MySQL is already running on the host — drop the `ports:` mapping on the `mysql` service, or map `3307:3306` |
| Files owned by `root` on the host | UID mismatch — rebuild with `docker compose build --build-arg UID=$(id -u)` |
| Code changes not appearing | Editing inside the *image* rather than the bind-mounted folder, or the file lives in `.dockerignore` |
| `Class "Redis" not found` | The `redis` PHP extension is missing from the image — it is installed in this Dockerfile via PECL |

## Sail or Hand-Written?

| | Sail | Hand-written |
|---|---|---|
| Setup | One command | Write and maintain the files |
| Understanding | Hidden | Explicit |
| Customising | Publish and edit the files anyway | Already yours |
| Upgrading | `composer update` | Manual |

For learning, write it by hand once. For actual projects, Sail is the sensible default —
and if you outgrow it, `php artisan sail:publish` dumps its Dockerfiles into the project so you
can edit them directly.

## Key Information

Docker here solves *development environment* consistency — the same PHP version, extensions and
database on every machine, with nothing installed globally. That is a separate question from how
the app is deployed. Plenty of teams develop in Sail and deploy to a plain VPS with no containers
involved at all, which is exactly what [03](../03/README.md) walks through.
