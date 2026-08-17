# Laravel

Laravel is a full-stack PHP web framework. Where the [command-line scripts](../CLS/README.md)
run plain PHP files one at a time, Laravel gives a whole application structure —
routing, controllers, views, database migrations, and a development server.

[01 — Creating Your First App](./01/README.md)
Installing PHP and Composer, creating a project, and a walkthrough of routes, views,
controllers, models and migrations.

[02 — Running with Docker (Local Development)](./02/README.md)
Laravel Sail, and the equivalent hand-written `docker-compose.yml` with MySQL and Redis,
so nothing but Docker is installed on the machine.

[03 — Deploying to a VPS by Hand](./03/README.md)
Putting the app on a public Ubuntu server: nginx and PHP-FPM, HTTPS, queue workers,
the scheduler, and a deploy script.

[04 — Deploying as a Container Image](./04/README.md)
The same architecture built into images and pulled from a registry: multi-stage builds,
what has to move off the container filesystem, and rollback by image tag.

## Notes

Side topics that come up across the lessons rather than belonging to one of them.
The full index is at [Notes](./Notes/_Notes.md).

[Composer](./Notes/Composer.md) — `require` vs `global require`, the PATH gotcha,
`laravel/installer`, `install` vs `update`, and the production flags.

[DB Migration](./Notes/DBMigration.md) — what `up()` and `down()` are for, batches and
rollbacks, and why an applied migration must never be edited.

[SQLite](./Notes/SQLite.md) — four ways to open `database.sqlite`, and the `sqlite3` prompt
traps: the transient in-memory database, and a missing dot turning a command into SQL.

[Sail](./Notes/Sail.md) — why both install commands are needed and in which order, what
`sail:install` writes, and the `.env` rewrite that orphans an existing SQLite file.

[Docker Init](./Notes/Docker-Init.md) — what actually starts a container: `command:` versus
`CMD`/`ENTRYPOINT`, why Sail's compose file needs neither, and `--host=0.0.0.0`.

[Octane State](./Notes/Octane-State.md) — how a persistent worker leaks request state between
users, how to fix it, and why queue workers already have the same problem.

[Redis](./Notes/Redis.md) — whether it is required (no), what it is actually used for, and
when it stops being optional.

## How These Fit Together

Lesson 02 and lessons 03/04 answer different questions:

- **02 is about the development environment** — a consistent PHP, database and cache on
  every machine that works on the project.
- **03 and 04 are about hosting** — what actually serves the app to the public. They are
  two answers to the same question, not sequential steps.

Plenty of teams develop in Docker (02) and deploy to a plain VPS (03) with no containers in
production at all. That combination is entirely normal.

| | 03 — VPS | 04 — Container image |
|---|---|---|
| Deploy is | `git pull` + rebuild on the server | Pull an image by tag |
| Server holds | Source, Composer, Node, PHP, nginx | Docker, and nothing else |
| Rollback | Re-run the deploy against an older commit | Point at the previous tag |
| Writing to disk | Fine — the server persists | Forbidden — the filesystem is disposable |
| Good for | One app, one server | Several servers, or CI-driven releases |

For a single small app, 03 is simpler and perfectly sound. 04 earns its extra machinery when
more than one server must stay identical, or when releases run through CI.

Managed platforms — Laravel Forge, Ploi, Laravel Cloud — automate the steps in 03 rather than
replacing them, so 03 is still the useful thing to have read when one of them misbehaves.