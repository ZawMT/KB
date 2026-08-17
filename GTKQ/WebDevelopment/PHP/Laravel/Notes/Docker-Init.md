# What Actually Starts a Container

[Back](../README.md)

## The Question

A diagram of a development stack usually shows the app container running `php artisan serve`. It
reads like an instruction — bring the containers up, then go and run that command inside one.

It is not. The server is **already running** the moment `docker compose up` returns. Nothing has
to be started by hand.

## A Container Is a Process

This is the idea everything else follows from. A container is not a small virtual machine that
you log into and start services inside. **A container is a process**, plus the filesystem and
network isolation wrapped around it.

`php artisan serve` does not run *inside* the app container so much as it *is* the app container.
When that process exits, the container stops — there is nothing else for it to be. That is why a
container with a mistyped command "starts and immediately dies": the process failed, so the
container is over.

It also means a container is never idle-but-empty. Every one of them was given something to run.
The only question is where that instruction was written.

## The Three Places It Can Come From

| Source | Written in | Notes |
| --- | --- | --- |
| `ENTRYPOINT` | Dockerfile | The fixed part; arguments get appended to it |
| `CMD` | Dockerfile | The default, easily replaced |
| `command:` | compose file | Overrides the image's `CMD` |

The practical rule: **`command:` in the compose file overrides `CMD` in the image.** An absent
`command:` does not mean "nothing runs" — it means "whatever the image already does".

## Case 1 — Spelled Out in Compose

The hand-written stack in [02](../02/README.md) puts it in the compose file:

```yaml
services:
  app:
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www/html
    command: php artisan serve --host=0.0.0.0 --port=8000
```

That line is PID 1 of the container. `docker compose up -d` starts it; the app answers on
`http://localhost:8000` straight away.

The Dockerfile there also carries a `CMD` with the same command, so `docker run` alone still
works — the compose `command:` simply overrides it with identical text.

## Case 2 — Baked Into the Image

Sail's generated `compose.yaml` has **no `command:` at all**:

```yaml
services:
    laravel.test:
        image: 'sail-8.5/app'
        ports:
            - '${APP_PORT:-80}:80'
        volumes:
            - '.:/var/www/html'
```

Nothing is missing. The image knows what to run, in a four-step chain inside
`vendor/laravel/sail/runtimes/<php-version>/`:

**1.** The Dockerfile ends with an entrypoint script:

```dockerfile
ENTRYPOINT ["start-container"]
```

**2.** `start-container`, called with no arguments, hands off to supervisor:

```bash
if [ $# -gt 0 ]; then
    exec gosu $WWWUSER "$@"          # arguments given? run those instead
else
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
```

**3.** `supervisord.conf` defines exactly one program:

```ini
[program:php]
command=%(ENV_SUPERVISOR_PHP_COMMAND)s
```

**4.** And its default is set back in the Dockerfile:

```dockerfile
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
```

So Sail runs the same `php artisan serve --host=0.0.0.0` as the hand-written stack. The
differences are port 80 rather than 8000, a supervisor wrapper, and the instruction living in the
image instead of the compose file.

**Why the supervisor wrapper.** It restarts PHP if the process dies, and it makes the command
swappable without rebuilding — setting `SUPERVISOR_PHP_COMMAND` in `.env` replaces it, which is
how people switch Sail to Octane.

**Why the `$@` branch.** Passing arguments to the container runs them *instead of* the server.
That is how one image serves both jobs: the long-running web process, and one-off commands.

## `command` Versus `exec`

Two different things, easily confused:

| | What it is | When it runs |
| --- | --- | --- |
| `command:` / `CMD` | The process that **defines** the container | At `up`, automatically |
| `docker compose exec` | An **extra** process alongside it | When you type it |

So the things you genuinely run by hand are one-off commands, into a container that is already up:

```bash
docker compose exec app php artisan migrate
./vendor/bin/sail artisan migrate          # the same thing, via Sail's wrapper
```

`sail` is a shell script that prefixes commands with `docker compose exec`. Nothing more.

## Things That Bite Beginners

**`--host=0.0.0.0` is not optional.** By default `artisan serve` binds to `127.0.0.1`, which
*inside a container* means that container alone. The port mapping then forwards to nothing and
the browser reports connection refused. `0.0.0.0` listens on every interface, so the mapping can
reach it. This is the single most common reason a correctly-started container appears dead.

**An empty `command:` is not a missing command.** Sail's compose file omits it because the image
supplies it. Adding your own `command:` there overrides Sail's entrypoint chain and usually
breaks it.

**A container exits when its process exits.** `php artisan serve` failing on a syntax error takes
the container with it. `docker compose ps` shows it stopped and `docker compose logs app` says
why — the logs outlive the container.

**Do not start the server manually as well.** `docker compose exec app php artisan serve` inside
an already-serving container gives "address already in use". The server is running; you are
looking for `exec` with a *different* command.

**Published ports differ between setups.** Sail maps `${APP_PORT:-80}:80`, so the app is at
`http://localhost` with no port. The hand-written stack maps `8000:8000`. Same server, different
published port — and `http://localhost:8000` on a Sail project simply will not answer.

**One process per container is the convention.** Supervisor here runs a single program. Queue
workers and schedulers belong in *additional* services in the compose file, not squeezed into the
app container.
