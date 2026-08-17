# Laravel Sail — Docker for the Development Environment

[Back](../README.md)

## What Sail Is

Sail is a **thin wrapper around Docker Compose**. It ships a `compose.yaml` describing the
services an application needs — PHP, MySQL, Redis, a mail catcher — plus a small `sail` script
that saves you typing `docker compose exec ...` all day.

It is not a replacement for `brew install php`, and it is not a deployment tool. Its purpose is
to run the *supporting services* without installing any of them natively: a real MySQL, a real
Redis, on every machine on the team, at identical versions.

A project on SQLite with a local PHP — the setup in [01](../01/README.md) — already works with
none of this. Sail earns its place when you want a database server, or when a teammate's
environment has to match yours exactly.

## Installing Into an Existing Project

Two commands, and **both** are needed. They are sequential, not alternatives:

```bash
cd testprj                             # must be inside the project
composer require laravel/sail --dev    # 1. add the package
php artisan sail:install               # 2. configure it
```

| Command | What it does |
| --- | --- |
| `composer require laravel/sail --dev` | Downloads the package into `vendor/`, adds it to `require-dev` |
| `php artisan sail:install` | Publishes `compose.yaml` and rewrites `.env` |

The reason the order is fixed: **`sail:install` is a command provided *by* the package that step 1
installs.** Run it first and artisan answers `Command "sail:install" is not defined` — it does not
exist until Sail is in `vendor/`.

`--dev` is correct: Sail is a development tool and has no business in a production install.

Step 2 asks which services to include:

```
Which services would you like to install?
 ○ mysql   ○ pgsql   ○ mariadb   ○ redis   ○ memcached
 ○ meilisearch   ○ minio   ○ mailpit   ○ selenium   ○ soketi
```

Your answers become the `compose.yaml`, and `.env` is updated so `DB_HOST` and friends point at
those containers instead of `localhost`. The `sail` service — the PHP container itself — is
always there in addition to whatever you picked.

> **The file is `compose.yaml`, not `docker-compose.yml`.** The Compose Spec made the shorter
> name canonical, and Docker looks for it first, so current Sail publishes only that. Older
> tutorials naming `docker-compose.yml` are describing the legacy filename; nothing is missing if
> you do not find one.

## Both Commands Need an Existing Project

Neither command creates anything — they add to a project that is already there.

`artisan` is not a globally installed tool. It is **a file in the project root**:

```
testprj/
├── artisan          ← this file
├── composer.json
└── ...
```

`php artisan ...` means "run the PHP script named `artisan` in the current directory". From
anywhere else the answer is `Could not open input file: artisan`. `composer require` is equally
project-local — it writes into a `composer.json`, so it needs one to exist.

So the full sequence from nothing is:

```bash
composer create-project laravel/laravel testprj   # 1. create the project
cd testprj                                        # 2. move into it
composer require laravel/sail --dev               # 3. add the package
php artisan sail:install                          # 4. configure it
```

## Starting With Sail Instead

To have Sail from the outset rather than bolting it on:

```bash
curl -s https://laravel.build/testprj | bash
```

This builds a new project with Sail already configured, and it does the whole thing **inside
Docker** — no PHP and no Composer are needed on the host machine, only Docker itself. That is why
Laravel's own documentation leads with it: it is the zero-prerequisites way in.

Services can be chosen up front with a query string:

```bash
curl -s "https://laravel.build/testprj?with=mysql,redis" | bash
```

## Running It

```bash
./vendor/bin/sail up          # start the containers
./vendor/bin/sail up -d       # ... in the background
./vendor/bin/sail down        # stop them
```

That path is tedious, so the usual move is a shell alias in `~/.zshrc`:

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Then everything shortens to `sail up`, `sail down`, and so on.

Once running, the application is at `http://localhost`.

## Everyday Commands

Each of these runs the tool **inside the container**, which is the entire point:

| Command | Equivalent outside Sail |
| --- | --- |
| `sail artisan migrate` | `php artisan migrate` |
| `sail artisan tinker` | `php artisan tinker` |
| `sail composer require ...` | `composer require ...` |
| `sail php -v` | `php -v` |
| `sail npm install` | `npm install` |
| `sail test` | `php artisan test` |
| `sail mysql` | a MySQL client session on the container's database |
| `sail shell` | a shell prompt inside the container |

## Things That Bite Beginners

**Docker Desktop has to be installed and running.** Sail issues Docker commands and nothing more.
If Docker is not running, `sail up` fails with a daemon connection error, which reads as a Sail
problem but is not one.

**`sail:install` rewrites `.env`.** Choosing MySQL at the prompt repoints `DB_CONNECTION` away
from SQLite — and an existing `database/database.sqlite` then holds data the application can no
longer see. The rows are not lost, merely orphaned; check the file after installing.

**Run artisan through `sail`, not directly.** After installing, `php artisan migrate` on the host
uses the host's PHP and tries to reach the database at the *container's* hostname, which the host
cannot resolve. `sail artisan migrate` runs inside the container where that hostname exists. The
mistake produces a connection error that looks like a broken database.

**Ports clash with whatever is already listening.** Sail wants port 80 for the app and 3306 for
MySQL. A local Apache or a native MySQL will already hold those. Set the ports in `.env` rather
than fighting over them:

```bash
APP_PORT=8080
FORWARD_DB_PORT=3307
```

**The first `sail up` is slow.** It downloads and builds images — several minutes is normal, and
nothing is wrong. Subsequent starts take seconds.

**`sail down -v` deletes the database.** Plain `down` stops the containers and keeps the data;
adding `-v` removes the volumes too, which discards everything in MySQL. That is the fast way to
start clean, and an unpleasant surprise otherwise.
