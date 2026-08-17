# Octane and Leaked State

[Back](../README.md)

## Two Execution Models

Traditional PHP is **shared-nothing**. Under nginx with PHP-FPM, every request gets a fresh
process state: the framework boots, the code runs, the response is sent, and the whole memory
image is discarded. The next request starts from nothing.

Laravel Octane — running on FrankenPHP, Swoole or RoadRunner — boots the framework **once** and
then loops: accept a request, handle it, respond, accept the next. Same process, same memory,
often for thousands of requests.

| | PHP-FPM | Octane worker |
| --- | --- | --- |
| Framework boot | Every request | Once, at startup |
| Memory after a response | Discarded entirely | Kept |
| Request isolation | Guaranteed by the runtime | Maintained by your code |
| Speed | Slower — pays boot each time | Faster — boot already paid |

The speed difference is the whole point of Octane. The isolation difference is the whole cost.

## What FPM Was Quietly Guaranteeing

Because FPM throws the process state away, **no data can travel between requests through
memory**. Not because the code was written carefully — because there is nowhere for it to
survive. Statics, singletons, mutated config: all gone before the next visitor arrives.

Under a persistent worker that guarantee disappears. Anything held at a scope that outlives a
single request now genuinely outlives it.

## The Bug This Enables

```php
class CurrentTenant
{
    public static ?Tenant $current = null;

    public static function get(): Tenant
    {
        return static::$current ??= auth()->user()->tenant;
    }
}
```

Under FPM this is safe. The static resets to `null` with the process, so each request does its own
lookup.

Under Octane, request 1 belongs to Alice and fills `static::$current` with her tenant. Request 2
belongs to Bob — `??=` finds a non-null value, skips the lookup entirely, and **Bob is served
Alice's tenant.**

Nothing crashes. Nothing appears in the logs. The wrong data is simply returned, to whoever
happens to reach that worker next.

That is why it is worth calling a *class* of bug rather than a bug:

- it is a **cross-request data leak**, so it is a security problem, not a cosmetic one;
- it fails **silently**, producing plausible wrong answers rather than errors;
- it appears only under **concurrency and reuse**, so a single manual test never shows it.

## The Same Shape, Elsewhere

**A singleton that captured the request.** Resolved once, then frozen — it holds the first
request's object for the life of the worker:

```php
$this->app->singleton(Report::class, fn ($app) => new Report($app['request']));
```

**Config mutated at runtime.** `config(['app.locale' => 'fr'])` inside one request stays `fr` for
every later request that worker handles.

**Anything accumulating.** A static array appended to per request grows without bound, because
the process that would have freed it never exits. Memory leaks that FPM made invisible become
real leaks here.

**Listeners and macros registered mid-request.** Registered again on each pass, they stack up.

## Fixing It

Three levels, in order of preference: rewrite the code, reset what cannot be rewritten, and bound
whatever was missed.

### 1. The Real Fix — Do Not Hold It

The tenant example, corrected:

```php
// Bad — the static survives the request
return static::$current ??= auth()->user()->tenant;

// Fine — resolved fresh each time
return auth()->user()->tenant;
```

When the lookup is genuinely expensive and deserves caching *within* one request, that is what
`scoped()` is for:

```php
// AppServiceProvider::register()
$this->app->scoped(CurrentTenant::class, fn () => auth()->user()->tenant);
```

`scoped()` behaves like `singleton()` — resolved once, reused — except that **Octane flushes it
between requests**. Under FPM the two are identical, so one version of the code is correct on
both. That property is what makes the habit cheap.

The captured-request singleton takes the same treatment:

```php
// Bad — freezes the first request's object for the life of the worker
$this->app->singleton(Report::class, fn ($app) => new Report($app['request']));

// Good — the request arrives at call time
$this->app->bind(Report::class, fn () => new Report());
$report->for($request);
```

| Instead of | Use |
| --- | --- |
| `$app->singleton(...)` holding request state | `$app->scoped(...)` — flushed between requests |
| A static property used as a cache | An instance property, or a real cache with an explicit key |
| `config([...])` at runtime | Nothing — treat config as read-only |
| Request data stashed on a service | Pass it as an argument |

### 2. If a Static Is Unavoidable — Reset It

For third-party code that cannot be rewritten, or a genuinely global registry, hook Octane's
per-request event in `config/octane.php`:

```php
'listeners' => [
    RequestReceived::class => [
        ...Octane::prepareApplicationForNextOperation(),
        FlushCurrentTenant::class,      // your listener
    ],
],
```

```php
class FlushCurrentTenant
{
    public function handle($event): void
    {
        CurrentTenant::$current = null;
    }
}
```

That `prepareApplicationForNextOperation()` line is Laravel doing exactly this for **its own**
state — auth, session, cookies and the request instance are all reset between requests by shipped
listeners. The framework is already handled. Your code and your dependencies' are not.

`config/octane.php` also carries **`warm`**, services resolved once at boot because they are safe
to share, and **`flush`**, container bindings reset between requests because they are not.

### 3. The Backstop

```bash
php artisan octane:start --max-requests=500
```

This fixes nothing. It retires each worker after 500 requests so that a leak nobody found has a
bounded blast radius. Insurance, not a solution.

`php artisan octane:reload` restarts the workers after a deploy — necessary because the old code
is already in memory and edited files are otherwise ignored.

### Finding Them

**A plain test reproduces it.** Laravel rebuilds the application between test *methods*, but not
between requests inside one method — so two requests in a single test share memory the way one
Octane worker does:

```php
it('does not leak tenant between users', function () {
    $this->actingAs($alice)->get('/dashboard')->assertSee('Alice Ltd');
    $this->actingAs($bob)->get('/dashboard')->assertSee('Bob Inc');   // fails if leaked
});
```

Two users, two requests, one application instance. That is the smallest thing that exhibits the
bug, and it runs in ordinary CI with Octane nowhere in sight.

**The shapes are greppable:**

```bash
grep -rn "static \$" app/       # mutable static properties
grep -rn "\->singleton(" app/   # then check each for request/auth/session use
grep -rn "config(\[" app/       # runtime config mutation
```

### The One Rule

> **Nothing derived from the current request — user, session, input, tenant, locale — may be
> stored at a scope that outlives the request.** Static properties and `singleton()` bindings are
> exactly that scope.

Applied consistently, the class of bug disappears rather than being patched case by case.

## Why Write This Way Regardless

Octane-safe code is a **strict superset**: all of it runs identically well under FPM. `scoped()`
is `singleton()` there, and resolving a value per request instead of caching it in a static costs
microseconds. So the discipline is close to free, and it keeps the deployment target an open
question instead of a decision baked into the source. Written the loose way, adopting Octane later
means auditing every static and singleton in the project — dependencies included.

**And a persistent worker may already be running.** Queue workers are long-lived processes too:

```bash
php artisan queue:work     # boots once, then processes thousands of jobs in one process
```

Identical memory model — a static holding job 1's tenant is visible to job 2. Any application
with a queue is already running part of its code this way, somewhere FPM's guarantee never
reached. It is also why `queue:restart` after a deploy exists, for precisely the reason
`octane:reload` does.

## Things That Bite Beginners

**Code changes do not appear.** The worker booted your application minutes ago and is still
serving it. `octane:reload` after every change, or run `--watch` during development.

**The bug never reproduces locally.** One developer clicking through a site gets one request at a
time and often a single worker with a clean state. Leaked state needs a *second* request through
the *same* worker to show itself, which is why staging with real traffic is where it surfaces.

**Package code is exposed too.** The discipline applies to every dependency, not only your own
code. A package written for FPM may hold request state in a static quite happily. Octane's docs
maintain a list of known-incompatible behaviour worth checking before switching.

**`dd()` and `dump()` behave differently.** Killing a persistent worker mid-request is not the
clean stop it is under FPM.

## When It Is Worth It

Octane is a response to a measured problem: framework boot time dominating the request. Reach for
it after profiling shows that, not before.

For learning, and for most small applications, FPM's process-per-request model is the forgiving
one — it makes an entire category of mistake impossible, and pays for that in milliseconds nobody
is counting. See [03](../03/README.md) for where each fits in a deployment.
