## C#
### `Lazy<T>`

#### [Back to C# contents](../_Contents.md)

*What is `Lazy<T>`, and when should you use it?*

Short answer: **`Lazy<T>` delays creating an object until the first time it's actually needed, creates it only once, and is thread-safe by default.**

> Note: in the code samples, a `// ❌` comment describes **the line it's on**.

### The problem: eager creation

```csharp
public class ReportService
{
    // Created as soon as ReportService is created, even if never used
    private readonly PdfEngine _pdf = new PdfEngine();   // slow: loads fonts, templates...

    public string GetSummary() => "Quick text summary";   // doesn't need _pdf
    public byte[] ExportPdf() => _pdf.Render();          // only this needs it
}
```

If most callers only use `GetSummary()`, every `ReportService` still pays for building a `PdfEngine`. Wasted time and memory.

### The fix: `Lazy<T>`

```csharp
public class ReportService
{
    private readonly Lazy<PdfEngine> _pdf = new(() => new PdfEngine());

    public string GetSummary() => "Quick text summary";   // PdfEngine NOT created
    public byte[] ExportPdf() => _pdf.Value.Render();    // created here, on first .Value
}
```

How it works:

| Member | Meaning |
| --- | --- |
| `new Lazy<T>(factory)` | Stores the **recipe** (the factory delegate). Nothing is created yet |
| `.Value` | First call: runs the factory, **caches** the result, returns it. Later calls: return the cached object |
| `.IsValueCreated` | `true` once the value has been created |

```csharp
var lazy = new Lazy<PdfEngine>(() =>
{
    Console.WriteLine("Creating PdfEngine...");
    return new PdfEngine();
});

Console.WriteLine(lazy.IsValueCreated);   // False
var a = lazy.Value;                       // prints "Creating PdfEngine..."
var b = lazy.Value;                       // prints nothing (cached)
Console.WriteLine(ReferenceEquals(a, b)); // True
Console.WriteLine(lazy.IsValueCreated);   // True
```

Output:

```
False
Creating PdfEngine...
True
True
```

Without a factory, `new Lazy<T>()` calls `T`'s **public parameterless constructor**:

```csharp
var lazy = new Lazy<PdfEngine>();   // uses new PdfEngine() on first .Value
```

### Why not just `??=`?

The manual version looks simpler:

```csharp
private PdfEngine? _pdf;
public PdfEngine Pdf => _pdf ??= new PdfEngine();
```

This is fine **in single-threaded code**. With multiple threads, two threads can both see `null` and both create a `PdfEngine`:

```
Thread 1: _pdf == null → creating...
Thread 2: _pdf == null → creating...   (two instances, one is thrown away)
```

`Lazy<T>` handles this for you. Making `??=` safe means writing locks by hand (double-checked locking), which is easy to get wrong.

| | `??=` | `Lazy<T>` |
| --- | --- | --- |
| Thread-safe | ❌ | ✅ (by default) |
| Code | Shortest | Short |
| Extra allocation | None | One `Lazy<T>` object + the delegate |
| Good for | Single-threaded, cheap objects | Shared, expensive objects |

### Thread-safety modes

The constructor takes a `LazyThreadSafetyMode` (or a `bool isThreadSafe`):

| Mode | Factory runs | Use when |
| --- | --- | --- |
| `ExecutionAndPublication` (**default**) | **Exactly once**; other threads wait | The factory is expensive or must not run twice |
| `PublicationOnly` | Possibly several times in parallel; **first result wins**, the rest are discarded | Factory is cheap and side-effect free; you want no locking |
| `None` | Once, but **no** thread safety | Only one thread ever touches it (slightly faster) |

```csharp
var lazy1 = new Lazy<Config>(LoadConfig);                                          // ExecutionAndPublication
var lazy2 = new Lazy<Config>(LoadConfig, LazyThreadSafetyMode.PublicationOnly);
var lazy3 = new Lazy<Config>(LoadConfig, isThreadSafe: false);                     // = None
```

Note: the thread safety is about **creating** the value. Once created, it's up to `T` itself to be safe for concurrent use.

### Exceptions are cached

With the default mode, if the factory **throws**, `Lazy<T>` **remembers the exception**. Every later `.Value` rethrows it, and the factory is **never retried**.

```csharp
int attempts = 0;
var lazy = new Lazy<string>(() =>
{
    attempts++;
    throw new TimeoutException("DB not ready");
});

try { _ = lazy.Value; } catch (TimeoutException) { }
try { _ = lazy.Value; } catch (TimeoutException) { }   // same exception, factory not run again

Console.WriteLine(attempts);   // 1
```

This surprises people when the failure is **temporary** (network blip, DB starting up): the `Lazy<T>` is "broken" for the rest of the app's life.

Options:

- Use `LazyThreadSafetyMode.PublicationOnly`: exceptions are **not** cached, so the next `.Value` retries.
- Replace the `Lazy<T>` instance when it fails.
- Handle retries inside the factory.

(Exception: when `new Lazy<T>()` uses the parameterless constructor, exceptions are not cached.)

### `Lazy<T>` for the Singleton pattern

The most common use: a thread-safe, lazily created single instance.

```csharp
public sealed class AppSettings
{
    private static readonly Lazy<AppSettings> _instance = new(() => new AppSettings());

    public static AppSettings Instance => _instance.Value;

    private AppSettings()
    {
        // load from file, environment, etc.
    }
}

var settings = AppSettings.Instance;   // created on first access, once, thread-safe
```

### Async: `Lazy<Task<T>>`

`Lazy<T>` has no async factory, and you shouldn't block with `.Result` inside it. Instead, make `T` a `Task<T>`:

```csharp
public class ExchangeRateService(HttpClient http)
{
    private readonly Lazy<Task<Dictionary<string, decimal>>> _rates =
        new(() => LoadRatesAsync(http));

    public async Task<decimal> GetRateAsync(string currency)
    {
        var rates = await _rates.Value;   // first caller starts the load; everyone awaits the same Task
        return rates[currency];
    }

    private static async Task<Dictionary<string, decimal>> LoadRatesAsync(HttpClient http)
    {
        var json = await http.GetStringAsync("https://example.com/rates");
        return JsonSerializer.Deserialize<Dictionary<string, decimal>>(json)!;
    }
}
```

- The factory runs **once** and returns a `Task`. All callers `await` that **same** task.
- Same caching problem: if the task **fails**, the failed task is cached, and every caller gets the same exception.

Libraries such as `Microsoft.VisualStudio.Threading` provide `AsyncLazy<T>`, which wraps this pattern.

### `LazyInitializer`: lazy without a wrapper

If you don't want a `Lazy<T>` object per field (e.g. many instances of a class), `LazyInitializer` works directly on a normal field:

```csharp
public class Customer
{
    private List<Order>? _orders;

    public List<Order> Orders =>
        LazyInitializer.EnsureInitialized(ref _orders, () => LoadOrders());

    private List<Order> LoadOrders() => new();
}
```

- No extra `Lazy<T>` allocation.
- Thread-safe, but the factory **may run more than once** in a race (like `PublicationOnly`). Pass a lock object overload if it must run once.

### `Lazy<T>` with dependency injection

Sometimes a service needs a dependency that's **expensive** and only **rarely** used. Injecting `Lazy<T>` delays creating it.

The built-in .NET container does **not** support `Lazy<T>` automatically. You register it yourself:

```csharp
builder.Services.AddScoped<IPdfEngine, PdfEngine>();
builder.Services.AddScoped(sp => new Lazy<IPdfEngine>(() => sp.GetRequiredService<IPdfEngine>()));

public class ReportService(Lazy<IPdfEngine> pdf)
{
    public string GetSummary() => "Quick text summary";
    public byte[] ExportPdf() => pdf.Value.Render();   // resolved only when needed
}
```

(Autofac supports `Lazy<T>` injection out of the box.)

Before doing this, consider whether the dependency is really expensive to construct. Constructors should be cheap; if a constructor is slow, fix that first.

### Common mistakes

```csharp
var lazy = new Lazy<PdfEngine>(() => new PdfEngine());

var engine = lazy;                           // compiles, but engine is the Lazy wrapper, not the PdfEngine
engine.Render();                             // ❌ CS1061: Lazy<PdfEngine> has no 'Render'
lazy.Value.Render();                         // ✅
```

- **Checking `.Value` to see if it exists** creates it. Use `.IsValueCreated` instead.
- **`Lazy<T>` in a short-lived object** for a cheap value: the wrapper costs more than it saves.
- **Debugger side effect**: viewing `.Value` in the debugger's watch window can trigger creation.
- **Disposable values**: `Lazy<T>` does not dispose `T`. If `T` is `IDisposable`, dispose it yourself, and only if `IsValueCreated` is `true`:

```csharp
public void Dispose()
{
    if (_pdf.IsValueCreated)
        _pdf.Value.Dispose();
}
```

### When to use / when not to

**Use when:**

- The object is **expensive** to create (time, memory, I/O) **and** might **not be needed**.
- Several threads might ask for it at the same time and it must be created **once** (e.g. Singleton).
- You want to **speed up startup** by deferring work until it's needed.

**Don't use when:**

- The object is cheap: just create it.
- It's always used right away: laziness adds nothing.
- Only one thread touches it: `??=` is simpler.

### Key points

- `Lazy<T>` = **create on first `.Value`, cache it, and return the same instance afterwards.**
- Thread-safe by default (`ExecutionAndPublication`): the factory runs **exactly once**.
- With the default mode, **exceptions are cached** too. Use `PublicationOnly` if you need retries.
- For async loading, use **`Lazy<Task<T>>`** and `await lazy.Value`.
- `LazyInitializer.EnsureInitialized` gives lazy init on a plain field without a wrapper object.
- `Lazy<T>` doesn't dispose the value. Dispose it yourself if `IsValueCreated`.
