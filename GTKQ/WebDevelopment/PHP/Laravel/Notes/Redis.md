# Redis in Laravel — What For, and Is It Required?

[Back](../README.md)

## Not Compulsory

Laravel has no hard dependency on Redis. A complete application — cache, sessions, queues, rate
limiting — runs without it.

The confusion comes from installers. `php artisan sail:install` offers Redis alongside MySQL, most
people tick it, and a container appears. That does not connect anything to it. A freshly generated
Laravel project ships with these defaults:

```ini
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
```

Not one of them points at Redis. It is possible to run a Redis container for weeks and never send
it a single command.

## What Redis Actually Is

An **in-memory key–value store**. Data lives in RAM rather than on disk, which makes reads and
writes roughly an order of magnitude faster than a database round trip — and means the data is
volatile by nature. That trade defines everything it is good for: things that are hot, small,
and survivable if lost.

Laravel can point four subsystems at it, each with alternatives:

| Subsystem | Default | Alternatives |
| --- | --- | --- |
| Cache | `database` | `file`, `array`, `redis`, `memcached` |
| Session | `database` | `file`, `cookie`, `redis` |
| Queue | `database` | `sync`, `redis`, SQS, Beanstalkd |
| Broadcasting | `log` | `reverb`, `pusher`, `redis` |

Rate limiting and `Cache::lock()` ride on whichever **cache** store is configured, so they follow
without separate setup.

The `database` drivers are not a degraded mode. They work because the default migrations create
the tables they need — `cache`, `jobs`, `job_batches`, `failed_jobs` and `sessions` — which is why
those tables appear in a new project nobody has customised. See
[DB Migration](./DBMigration.md).

## What the Use Cases Look Like

### Caching an expensive result

The most common one. A query or third-party call that is slow and does not change often:

```php
use Illuminate\Support\Facades\Cache;

$popular = Cache::remember('notes.popular', 600, function () {
    return Note::withCount('views')->orderByDesc('views_count')->take(10)->get();
});
```

Ten minutes of hits are served from memory instead of hitting the database. The code is identical
whichever store is configured — swapping `CACHE_STORE` changes where it lands, not how it is
written.

### Sessions across several servers

With one server, `file` sessions are fine. Put two behind a load balancer and a user whose second
request lands on the other machine finds themselves logged out. `database` solves it; `redis`
solves it without adding write load to the database on every single request.

### Queues — work pushed out of the request

```php
ProcessUpload::dispatch($upload);      // returns immediately
```

The user gets their response while a worker handles the job. Both `database` and `redis` do this.
The difference is mechanism: the database driver **polls**, repeatedly running
`SELECT … FOR UPDATE` to claim jobs, which is fine at a few jobs a minute and wasteful at
thousands. Redis was built for the pattern.

### Rate limiting

```php
RateLimiter::for('api', fn (Request $request) =>
    Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
);
```

Counters incremented on every request and expiring on their own — precisely what an in-memory
store is for. This works on the `database` cache too, at the cost of a write per request.

### Atomic locks

Stopping the same work from running twice when several workers or servers are involved:

```php
Cache::lock('rebuild-sitemap', 120)->block(5, function () {
    // only one process is inside this block, cluster-wide
});
```

### Counters and other live numbers

Redis can also be used directly, bypassing the cache abstraction:

```php
use Illuminate\Support\Facades\Redis;

Redis::incr("views:note:{$note->id}");
Redis::zincrby('leaderboard', 1, $user->id);
```

Page-view counts, leaderboards, "N users online" — high-frequency writes where a database row
would be a bottleneck and losing a few counts is survivable.

### Broadcasting

Real-time features (live notifications, a chat panel) publish events through Redis to a WebSocket
server. Laravel Reverb can use it as its backend.

## Switching a Project Over

Nothing in application code changes — only `.env`:

```ini
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis          # the container's service name, not 127.0.0.1
REDIS_PORT=6379
```

`REDIS_HOST` follows the same rule as `DB_HOST` under Docker: containers reach each other by
**service name**. See [Docker Init](./Docker-Init.md).

`REDIS_CLIENT` picks how PHP talks to it — **phpredis** is a compiled C extension and the faster
choice (Sail's image includes it); **predis** is a pure-PHP library needing no extension.

## When It Stops Being Optional

| Situation | Why |
| --- | --- |
| Session and cache writes competing with real queries | Every request writes a session row to the same database serving your application |
| Thousands of queued jobs | Polling a table does not scale; Redis is purpose-built |
| More than one application server | `file` cache and sessions are per-machine and cannot be shared |
| **Laravel Horizon** | The one genuine requirement — Horizon only supervises Redis queues |
| Cluster-wide locks and counters | Possible on a database, natural on Redis |

## Things That Bite Beginners

**It is memory, so it can vanish.** A container restart loses anything not persisted. That is
acceptable for cache, and emphatically not for queued jobs — a lost job is work that silently
never happened. Redis does offer persistence (RDB snapshots, AOF), but the defaults are tuned for
speed rather than durability.

**`Cache::flush()` empties the whole Redis database.** It issues `FLUSHDB`, not a prefix-aware
delete. If sessions or queues share that database index, clearing the cache logs everybody out and
discards pending jobs. Laravel's `config/database.php` guards against this by defining separate
`default` and `cache` Redis connections on different database numbers — worth keeping:

```ini
REDIS_DB=0
REDIS_CACHE_DB=1
```

**Eviction can eat your queue.** Redis configured with a `maxmemory` policy discards keys when
full. Harmless for cache, destructive for jobs. Keep queues on a connection that does not evict.

**Installing it does not use it.** The container running is not the same as the drivers pointing
at it. Check `.env`, not `docker compose ps`.

**It is not a database.** Redis complements the database, it does not replace it. Anything you
would be upset to lose belongs in MySQL or PostgreSQL.

## For a Learning Project

Leave the defaults. The `database` drivers are fully supported, need no extra service, and keep
everything inspectable with ordinary SQL — see [SQLite](./SQLite.md).

If Sail has already installed Redis, an idle container costs a few megabytes and nothing else;
leaving it means `CACHE_STORE=redis` is one line away when you want to try it. To remove it
instead, delete the `redis:` service and the `sail-redis` volume from `compose.yaml`, and the
`REDIS_*` lines from `.env`.

Redis is a **performance decision, not a Laravel requirement** — the same shape of argument as
FPM versus Octane in [Octane State](./Octane-State.md). Adopt it when something measured calls
for it.
