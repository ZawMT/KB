# Singleton Pattern

> **Category:** Creational
> **Intent:** Ensure a class has **only one instance**, and provide a **global access point** to it.

## Contents

- [The Problem](#the-problem)
- [Core Ingredients](#core-ingredients)
- [Implementations in C#](#implementations-in-c)
  - [1. Naive (NOT thread-safe)](#1-naive-not-thread-safe)
  - [2. Simple lock](#2-simple-lock)
  - [3. Double-checked locking](#3-double-checked-locking)
  - [4. Static initialization](#4-static-initialization)
  - [5. `Lazy<T>` (recommended)](#5-lazyt-recommended)
  - [Comparison](#comparison)
- [Modern C#: Singleton via Dependency Injection](#modern-c-singleton-via-dependency-injection)
- [Singleton vs. Static Class](#singleton-vs-static-class)
- [Pitfalls](#pitfalls)
- [When to Use / When to Avoid](#when-to-use--when-to-avoid)
- [Key Takeaways](#key-takeaways)

---

## The Problem

Some resources should exist **exactly once** in an application:

- Application configuration
- Logger
- Cache
- Connection pool / `HttpClient`
- Hardware access (printer spooler, etc.)

If every caller does `new Logger()`, you end up with multiple instances that may conflict (e.g. two loggers writing to the same file), waste memory, or hold inconsistent state.

Singleton solves this by letting **the class itself** control how many instances exist.

---

## Core Ingredients

Every classic Singleton has three parts:

| Ingredient | Purpose |
| --- | --- |
| `private` constructor | Nobody outside can call `new` |
| `private static` field | Holds the single instance |
| `public static` property/method | The global access point (`Instance`) |

Also usually:

- Mark the class `sealed` — a subclass could otherwise create extra instances.

---

## Implementations in C#

### 1. Naive (NOT thread-safe)

```csharp
public sealed class Logger
{
    private static Logger? _instance;

    private Logger() { }

    public static Logger Instance
    {
        get
        {
            if (_instance == null)          // (A)
            {
                _instance = new Logger();   // (B)
            }
            return _instance;
        }
    }

    public void Log(string message) => Console.WriteLine($"[{DateTime.Now:T}] {message}");
}
```

**Problem:** Two threads can both pass check (A) before either reaches (B) → **two instances** get created.

```
Thread 1: _instance == null ? yes ──┐
Thread 2: _instance == null ? yes ──┤  both enter
Thread 1: _instance = new Logger()  │
Thread 2: _instance = new Logger()  ┘  second one overwrites the first
```

Fine for single-threaded demos only.

---

### 2. Simple lock

```csharp
public sealed class Logger
{
    private static Logger? _instance;
    private static readonly object _lock = new();

    private Logger() { }

    public static Logger Instance
    {
        get
        {
            lock (_lock)
            {
                _instance ??= new Logger();
                return _instance;
            }
        }
    }
}
```

- ✅ Thread-safe
- ❌ Takes a lock on **every** access, even after the instance already exists → small performance cost.

---

### 3. Double-checked locking

```csharp
public sealed class Logger
{
    private static volatile Logger? _instance;
    private static readonly object _lock = new();

    private Logger() { }

    public static Logger Instance
    {
        get
        {
            if (_instance == null)              // 1st check: no lock (fast path)
            {
                lock (_lock)
                {
                    if (_instance == null)      // 2nd check: inside lock
                    {
                        _instance = new Logger();
                    }
                }
            }
            return _instance;
        }
    }
}
```

- ✅ Thread-safe, locks only during first creation
- ❌ Easy to get wrong (`volatile` matters for memory ordering); more code than needed in .NET
- Mostly of historical interest — options 4 and 5 are simpler.

---

### 4. Static initialization

```csharp
public sealed class Logger
{
    private static readonly Logger _instance = new();

    // Explicit static constructor: tells the compiler not to mark the type
    // as 'beforefieldinit', so initialization happens on first access.
    static Logger() { }

    private Logger() { }

    public static Logger Instance => _instance;
}
```

- ✅ Thread-safe — the CLR guarantees a static initializer runs **exactly once**
- ✅ Very short
- ⚠️ Less lazy: the instance is created when the class is first touched (any static member), not necessarily when `Instance` is accessed.

---

### 5. `Lazy<T>` (recommended)

```csharp
public sealed class Logger
{
    private static readonly Lazy<Logger> _lazy = new(() => new Logger());

    private Logger()
    {
        Console.WriteLine("Logger created");
    }

    public static Logger Instance => _lazy.Value;

    public void Log(string message) => Console.WriteLine($"[{DateTime.Now:T}] {message}");
}
```

- ✅ Thread-safe by default (`LazyThreadSafetyMode.ExecutionAndPublication`)
- ✅ Truly lazy — created on the first `.Value` call
- ✅ Clear intent, little code

Usage:

```csharp
Logger.Instance.Log("App started");
Logger.Instance.Log("Doing work");

Console.WriteLine(ReferenceEquals(Logger.Instance, Logger.Instance)); // True
```

Output:

```
Logger created
[10:15:02] App started
[10:15:02] Doing work
True
```

Note: `"Logger created"` prints **once**.

---

### Comparison

| Version | Thread-safe | Lazy | Complexity | Verdict |
| --- | --- | --- | --- | --- |
| 1. Naive | ❌ | ✅ | Low | Avoid |
| 2. Simple lock | ✅ | ✅ | Low | OK, slight overhead |
| 3. Double-checked | ✅ | ✅ | High | Unnecessary in .NET |
| 4. Static init | ✅ | ~ | Very low | Good |
| 5. `Lazy<T>` | ✅ | ✅ | Low | **Best default** |

---

## Modern C#: Singleton via Dependency Injection

In modern .NET apps (ASP.NET Core, Worker Services, MAUI), you usually **don't write the pattern by hand**. Instead, you let the DI container manage the lifetime:

```csharp
public interface IAppLogger
{
    void Log(string message);
}

// Normal class: public constructor, no static Instance
public class ConsoleAppLogger : IAppLogger
{
    public void Log(string message) => Console.WriteLine(message);
}
```

```csharp
// Program.cs
var builder = WebApplication.CreateBuilder(args);

builder.Services.AddSingleton<IAppLogger, ConsoleAppLogger>(); // one instance for the app's lifetime

var app = builder.Build();
```

```csharp
public class OrderService
{
    private readonly IAppLogger _logger;

    public OrderService(IAppLogger logger)   // injected, not fetched globally
    {
        _logger = logger;
    }

    public void PlaceOrder() => _logger.Log("Order placed");
}
```

Why this is preferred:

- The **container** guarantees one instance — the class itself stays ordinary.
- Dependencies are **explicit** (visible in the constructor).
- Easy to **swap in a fake** for unit tests.

DI lifetimes for reference:

| Lifetime | Instances |
| --- | --- |
| `AddSingleton` | One for the whole app |
| `AddScoped` | One per scope (per HTTP request in ASP.NET Core) |
| `AddTransient` | New one every time it's requested |

⚠️ **Captive dependency:** a singleton must not depend on a scoped/transient service — the singleton would hold on to it forever.

---

## Singleton vs. Static Class

| | Singleton | Static class |
| --- | --- | --- |
| Instance | One object | No object at all |
| Implement interfaces | ✅ | ❌ |
| Pass as parameter / inject | ✅ | ❌ |
| Lazy initialization | ✅ | Limited |
| Inheritance / polymorphism | Possible (via interface) | ❌ |
| Mockable in tests | ✅ (via interface) | ❌ |
| Good for | Stateful shared services | Stateless helpers (`Math`, extension methods) |

Rule of thumb: **no state + pure functions → static class**; **shared state or needs an interface → singleton**.

---

## Pitfalls

1. **Hidden dependencies** — `Logger.Instance` can be called from anywhere, so a class's dependencies aren't visible from its constructor.
2. **Hard to unit test** — you can't easily replace `Logger.Instance` with a fake. Fix: depend on an interface, or use DI.
3. **Global mutable state** — any code can change it; bugs become hard to trace.
4. **Thread safety of the *members*** — the pattern only makes *creation* thread-safe. If the singleton has mutable fields, its methods need their own synchronization:

   ```csharp
   public sealed class Counter
   {
       private static readonly Lazy<Counter> _lazy = new(() => new Counter());
       public static Counter Instance => _lazy.Value;
       private Counter() { }

       private int _count;

       public int Increment() => Interlocked.Increment(ref _count); // not _count++
   }
   ```

5. **Violates Single Responsibility** — the class does its real job *and* manages its own lifetime.
6. **"One per what?"** — a static singleton is one per **AppDomain/process**. Multiple servers, processes, or test runners each get their own copy.

---

## When to Use / When to Avoid

**Use when:**

- Exactly one instance must exist (config, cache, logger, hardware gateway).
- The object is expensive to create and can be safely shared.
- You're **not** in a DI-based app (console tools, small libraries).

**Avoid when:**

- You just want "a convenient global variable".
- You're in an app with a DI container → use `AddSingleton` instead.
- The object holds per-user or per-request data.

---

## Key Takeaways

- Singleton = **private constructor + static instance + static access point**.
- In C#, prefer **`Lazy<T>`** (or static initialization) over hand-rolled locking.
- In modern .NET, prefer **DI `AddSingleton`** over the classic pattern — same "one instance" result, without the testability problems.
- Creation being thread-safe ≠ the object being thread-safe.
