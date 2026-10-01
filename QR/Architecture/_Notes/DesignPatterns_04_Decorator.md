# Decorator Pattern

> **Category:** Structural
> **Intent:** Attach **additional responsibilities** to an object **dynamically**, by wrapping it in another object that has the **same interface**. A flexible alternative to subclassing for extending behavior.

Also known as: **Wrapper**.

## Contents

- [The Problem](#the-problem)
- [Real-World Analogy](#real-world-analogy)
- [Structure](#structure)
- [C# Example: Caching, Logging and Retry](#c-example-caching-logging-and-retry)
  - [Stacking Decorators](#stacking-decorators)
  - [Order Matters](#order-matters)
- [Classic Example: Coffee Add-ons](#classic-example-coffee-add-ons)
- [Decorators You Already Use in .NET](#decorators-you-already-use-in-net)
- [Decorator with Dependency Injection](#decorator-with-dependency-injection)
- [Decorator vs. Inheritance](#decorator-vs-inheritance)
- [Decorator vs. Similar Patterns](#decorator-vs-similar-patterns)
- [Pitfalls](#pitfalls)
- [When to Use / When to Avoid](#when-to-use--when-to-avoid)
- [Key Takeaways](#key-takeaways)

---

## The Problem

You have a repository:

```csharp
public interface IProductRepository
{
    Product? GetById(int id);
}

public class SqlProductRepository : IProductRepository
{
    public Product? GetById(int id)
    {
        Console.WriteLine($"  [SQL] SELECT * FROM Products WHERE Id = {id}");
        return new Product(id, $"Product {id}");
    }
}

public record Product(int Id, string Name);
```

Now you want **caching**, **logging** and sometimes **retry**. Options:

**❌ Put it all inside `SqlProductRepository`**

```csharp
public Product? GetById(int id)
{
    _logger.Log(...);
    if (_cache.TryGetValue(id, out var p)) return p;
    for (int attempt = 0; attempt < 3; attempt++) { try { ... } catch { ... } }
    ...
}
```

The class now does four jobs (violates **Single Responsibility**), and you can't turn features on/off independently.

**❌ Use inheritance**

```
SqlProductRepository
 ├── CachedSqlProductRepository
 ├── LoggedSqlProductRepository
 ├── CachedLoggedSqlProductRepository
 ├── CachedLoggedRetrySqlProductRepository
 └── ... (one subclass per combination — "class explosion")
```

And all of these work **only for SQL**; a `MongoProductRepository` needs the whole set again.

**✅ Decorators**: one small class per feature, each wrapping an `IProductRepository`. Combine them at runtime like layers.

---

## Real-World Analogy

**Clothing.** You're still *you* (same interface), but you can put on a sweater, then a jacket, then a raincoat. Each layer adds something, can be added or removed independently, and the order you put them on matters.

---

## Structure

```
              IComponent  ◄──────────────────────────┐
             + Operation()                           │
                  ▲                                  │ wraps (has-a)
      ┌───────────┴────────────┐                     │
ConcreteComponent        Decorator (abstract) ───────┘
 + Operation()            - inner: IComponent
                          + Operation() → inner.Operation()
                                ▲
                   ┌────────────┴────────────┐
           LoggingDecorator           CachingDecorator
   Operation(): log; inner.Op()   Operation(): cache? : inner.Op()
```

| Role | In the example |
| --- | --- |
| **Component** | `IProductRepository` |
| **Concrete Component** | `SqlProductRepository` — the real work |
| **Decorator** | Implements `IProductRepository` **and** holds an inner `IProductRepository` |
| **Concrete Decorators** | `CachingProductRepository`, `LoggingProductRepository`, `RetryProductRepository` |

Key point: a decorator **is-a** component (same interface) and **has-a** component (the one it wraps). That's what lets them stack.

---

## C# Example: Caching, Logging and Retry

```csharp
public class LoggingProductRepository : IProductRepository
{
    private readonly IProductRepository _inner;

    public LoggingProductRepository(IProductRepository inner) => _inner = inner;

    public Product? GetById(int id)
    {
        Console.WriteLine($"[Log] GetById({id}) started");
        var sw = Stopwatch.StartNew();

        var product = _inner.GetById(id);          // delegate to the wrapped object

        Console.WriteLine($"[Log] GetById({id}) finished in {sw.ElapsedMilliseconds} ms");
        return product;
    }
}

public class CachingProductRepository : IProductRepository
{
    private readonly IProductRepository _inner;
    private readonly Dictionary<int, Product?> _cache = new();

    public CachingProductRepository(IProductRepository inner) => _inner = inner;

    public Product? GetById(int id)
    {
        if (_cache.TryGetValue(id, out var cached))
        {
            Console.WriteLine($"  [Cache] hit for {id}");
            return cached;                          // short-circuit: inner is NOT called
        }

        Console.WriteLine($"  [Cache] miss for {id}");
        var product = _inner.GetById(id);
        _cache[id] = product;
        return product;
    }
}

public class RetryProductRepository : IProductRepository
{
    private readonly IProductRepository _inner;
    private readonly int _maxAttempts;

    public RetryProductRepository(IProductRepository inner, int maxAttempts = 3)
    {
        _inner = inner;
        _maxAttempts = maxAttempts;
    }

    public Product? GetById(int id)
    {
        for (int attempt = 1; ; attempt++)
        {
            try
            {
                return _inner.GetById(id);
            }
            catch (TimeoutException) when (attempt < _maxAttempts)
            {
                Console.WriteLine($"  [Retry] attempt {attempt} failed, retrying...");
            }
        }
    }
}
```

Each decorator:

1. Implements `IProductRepository`.
2. Takes an `IProductRepository` in its constructor.
3. Does its extra work **before and/or after** calling `_inner`.

### Stacking Decorators

```csharp
IProductRepository repo =
    new LoggingProductRepository(
        new CachingProductRepository(
            new SqlProductRepository()));

repo.GetById(1);
Console.WriteLine();
repo.GetById(1);
```

Output:

```
[Log] GetById(1) started
  [Cache] miss for 1
  [SQL] SELECT * FROM Products WHERE Id = 1
[Log] GetById(1) finished in 2 ms

[Log] GetById(1) started
  [Cache] hit for 1
[Log] GetById(1) finished in 0 ms
```

The call flows **through the layers like an onion**:

```
caller → Logging → Caching → Sql
caller ← Logging ← Caching ← Sql
```

The caller only sees `IProductRepository` — it doesn't know (or care) how many layers there are.

### Order Matters

```csharp
// A: Logging outside Caching → every call is logged (hits and misses)
new LoggingProductRepository(new CachingProductRepository(sql));

// B: Caching outside Logging → only cache MISSES are logged
new CachingProductRepository(new LoggingProductRepository(sql));
```

Similarly, **Retry inside Cache** retries only real DB calls; **Retry outside Cache** would also wrap cache lookups (pointless). Think about the order like middleware.

---

## Classic Example: Coffee Add-ons

The textbook example, useful for seeing how decorators **accumulate** values:

```csharp
public interface IBeverage
{
    string Description { get; }
    decimal Cost { get; }
}

public class Espresso : IBeverage
{
    public string Description => "Espresso";
    public decimal Cost => 2.00m;
}

// Abstract base decorator: forwards everything by default
public abstract class BeverageDecorator(IBeverage inner) : IBeverage
{
    protected IBeverage Inner { get; } = inner;
    public virtual string Description => Inner.Description;
    public virtual decimal Cost => Inner.Cost;
}

public class Milk(IBeverage inner) : BeverageDecorator(inner)
{
    public override string Description => Inner.Description + ", Milk";
    public override decimal Cost => Inner.Cost + 0.50m;
}

public class Caramel(IBeverage inner) : BeverageDecorator(inner)
{
    public override string Description => Inner.Description + ", Caramel";
    public override decimal Cost => Inner.Cost + 0.70m;
}
```

```csharp
IBeverage drink = new Caramel(new Milk(new Milk(new Espresso())));

Console.WriteLine($"{drink.Description} = {drink.Cost:C}");
// Espresso, Milk, Milk, Caramel = $3.70
```

The abstract `BeverageDecorator` base is optional — it's handy when the interface has **many members** and each decorator only changes a few.

---

## Decorators You Already Use in .NET

**Streams** are the classic .NET decorator chain. Every one is a `Stream` that wraps another `Stream`:

```csharp
using var file   = File.OpenWrite("data.gz");                       // concrete component
using var gzip   = new GZipStream(file, CompressionLevel.Optimal);   // decorator: compresses
using var buffer = new BufferedStream(gzip);                         // decorator: buffers
using var writer = new StreamWriter(buffer);                         // adapter: bytes → text

writer.WriteLine("Hello, decorated world");
```

Others:

| .NET type | Adds |
| --- | --- |
| `BufferedStream`, `GZipStream`, `CryptoStream` | Buffering, compression, encryption on any `Stream` |
| `DelegatingHandler` (HttpClient) | Logging, auth headers, retry around HTTP calls |
| ASP.NET Core **middleware** | Each middleware wraps the next one in the pipeline |
| MediatR **pipeline behaviors** | Validation, logging, transactions around handlers |

`DelegatingHandler` example:

```csharp
public class AuthHeaderHandler : DelegatingHandler
{
    protected override Task<HttpResponseMessage> SendAsync(
        HttpRequestMessage request, CancellationToken ct)
    {
        request.Headers.Authorization = new("Bearer", "<TOKEN_PLACEHOLDER>");
        return base.SendAsync(request, ct);   // calls the inner handler
    }
}

builder.Services.AddTransient<AuthHeaderHandler>();
builder.Services.AddHttpClient("api")
       .AddHttpMessageHandler<AuthHeaderHandler>();
```

---

## Decorator with Dependency Injection

The built-in container has **no built-in "decorate" method**, so you wire it with a factory:

```csharp
builder.Services.AddScoped<SqlProductRepository>();

builder.Services.AddScoped<IProductRepository>(sp =>
    new LoggingProductRepository(
        new CachingProductRepository(
            sp.GetRequiredService<SqlProductRepository>())));
```

Consumers just ask for `IProductRepository` and get the full stack:

```csharp
public class ProductService(IProductRepository repo) { ... }
```

With the popular **Scrutor** library it's cleaner:

```csharp
builder.Services.AddScoped<IProductRepository, SqlProductRepository>();
builder.Services.Decorate<IProductRepository, CachingProductRepository>();
builder.Services.Decorate<IProductRepository, LoggingProductRepository>();   // outermost
```

⚠️ Lifetime: a caching decorator usually wants to live long (singleton), but if it wraps a **scoped** repository (e.g. using `DbContext`), that's a captive dependency. Use a shared cache service (`IMemoryCache`, singleton) **inside** a scoped decorator instead of a dictionary field.

---

## Decorator vs. Inheritance

| | Inheritance | Decorator |
| --- | --- | --- |
| When behavior is chosen | Compile time | **Runtime** |
| Combining features | One subclass per combination | Stack any combination |
| Works across implementations | Only for that base class | For **any** implementation of the interface |
| Sealed classes | ❌ | ✅ |
| Principle | — | **Composition over inheritance**, Open/Closed, SRP |

---

## Decorator vs. Similar Patterns

| Pattern | Interface | Purpose |
| --- | --- | --- |
| **Decorator** | Same | **Add** behavior; designed to be **stacked** |
| **Proxy** | Same | **Control access** (lazy load, auth, remote); usually one, often creates the real object itself |
| **Adapter** | **Different** | Make an incompatible interface fit |
| **Chain of Responsibility** | Same | Any handler may **stop** the chain and handle the request itself |

Decorator and Proxy look almost identical in code — the difference is **intent**.

---

## Pitfalls

1. **Large interfaces** — if `IProductRepository` has 20 methods, every decorator must implement all 20 (mostly forwarding). Use an abstract base decorator, or keep interfaces small (**Interface Segregation**).
2. **Order bugs** — the wrong stacking order gives subtle bugs (e.g. caching *outside* authorization → users get cached data they shouldn't see).
3. **Debugging** — deep stacks make stack traces and stepping through code longer.
4. **Identity checks** — `repo is SqlProductRepository` is `false` once wrapped. Don't type-check through decorators.
5. **Thread safety** — a `Dictionary` cache in a singleton decorator isn't thread-safe; use `ConcurrentDictionary` or `IMemoryCache`.
6. **Too many tiny decorators** — sometimes a single well-named class is clearer.

---

## When to Use / When to Avoid

**Use when:**

- You need **cross-cutting concerns** (logging, caching, retry, validation, metrics, authorization) without touching the core class.
- Features must be **combined** or **switched on/off** at runtime / via config.
- Subclassing would cause a **class explosion**, or the class is `sealed`.

**Avoid when:**

- You only ever need one fixed combination — a single class is simpler.
- The interface is huge and you can't split it.
- The behavior really belongs **inside** the core logic (it's not a separate concern).

---

## Key Takeaways

- Decorator = **same interface + wraps an instance of that interface + adds behavior before/after delegating**.
- Decorators **stack**; the **order** of stacking changes behavior.
- Great for **cross-cutting concerns**: logging, caching, retry, metrics.
- .NET is full of them: **Streams**, **`DelegatingHandler`**, **middleware**.
- In DI, wire them with a **factory registration** or **Scrutor's `Decorate`**.
