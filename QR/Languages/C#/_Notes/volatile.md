## C#
### `volatile`

#### [Back to C# contents](../_Contents.md)

*What does the `volatile` keyword do, and when should you use it?*

Short answer: **`volatile` on a field makes every read fetch the latest value and stops the compiler, JIT and CPU from reordering reads/writes around it in harmful ways. It's about *visibility* between threads, not *atomicity*: `volatile` does not make `count++` thread-safe.** In everyday code, `lock`, `Interlocked` or `CancellationToken` are usually better choices.

> Each sample is a complete program. The visibility problems only show up in **optimised (Release) builds**, so run them with `dotnet run -c Release`. In Debug builds, the JIT doesn't optimise enough to show the bug.

### Intuition: "this field can change at any time"

*Volatile* means "liable to change unexpectedly". The keyword roughly tells the compiler, the JIT and the CPU:

> "Other threads read and write this field, so it may change at any moment. Don't assume you know its value, don't keep a cached copy, and don't shuffle the code around it."

Two refinements make the intuition precise:

- **It's also about ordering**, not only fresh values: it limits which *other* reads and writes may be moved past it (see [Acquire and release](#acquire-and-release-semantics)).
- **Each single read and each single write is fresh, but combinations aren't protected.** `count++` (read, add, write) can still be interrupted in between.

In C and C++ the original meaning was "changed by **hardware** at any time" (e.g. a device register). C# reuses the word for "changed by **other threads**".

### The problem: a thread may never see another thread's write

For speed, the compiler, the JIT and the CPU are allowed to:

1. **Cache** a field's value (e.g. in a CPU register) instead of reading memory every time.
2. **Reorder** reads and writes, as long as the result looks the same *to a single thread*.

Both are invisible in single-threaded code, but they can break code where one thread writes a field and another reads it.

These optimisations exist to make **programs run faster**, and they all follow one rule:

> **The "as-if" rule:** any change is allowed as long as a **single thread** can't tell the difference.

#### Who reorders, and why

| Layer | What it does | Why it's faster |
|---|---|---|
| **C# compiler** (`csc`) | Very little reordering; mostly simple optimisations | — |
| **JIT** (when IL becomes machine code) | Keeps a field in a **CPU register** instead of re-reading memory; **hoists** reads out of loops; removes "redundant" reads; reorders instructions | Registers are about 100× faster than main memory |
| **CPU** (in hardware) | **Out-of-order execution**: runs later instructions while an earlier one waits. **Store buffers**: a write is parked in a per-core buffer before other cores can see it | The CPU doesn't stall while waiting for slow memory |

**JIT example: hoisting a read out of a loop**

```csharp
// As written:
while (!Stop) { loops++; }

// What the JIT may produce (Stop is read once, then kept in a register):
if (!Stop) { while (true) { loops++; } }
```

Nothing inside the loop changes `Stop`, so for a single thread reading it once is "the same", and skipping a memory read on every iteration is much faster. Sample 1 below shows the consequence.

**CPU example: not waiting on a slow write**

```csharp
_data = 42;      // suppose this memory isn't in the cache: slow (many CPU cycles)
_ready = true;   // this one is in the cache: fast
```

Instead of stalling, the CPU may let `_ready = true` become **visible to other cores first**. For the writing thread the result is the same (both end up set), but another core might see `_ready == true` while `_data` is still 0. Sample 3 below shows the fix.

#### Why each core can see a different order

Each CPU core has its own **caches** and **store buffer**:

```
Core 1:  registers → store buffer → L1/L2 cache ─┐
                                                  ├─ shared L3 cache / RAM
Core 2:  registers → store buffer → L1/L2 cache ─┘
```

A write travels through these layers to reach other cores, and writes don't always arrive in the order they were made. How much reordering a CPU allows varies:

- **x86/x64 (Intel, AMD):** fairly strict. Mostly, only a write followed by a read can be reordered.
- **ARM (Apple Silicon, phones, many servers):** more relaxed. Many kinds of reordering are allowed.

So some threading bugs appear on ARM machines but not on Intel/AMD ones.

#### Where `volatile` fits

These optimisations are on **by default** because nearly all code is single-threaded, or shares data through `lock` or `Interlocked`, which already include the necessary barriers. `volatile` tells the JIT and the CPU: **"for this field, give up some of that speed and keep the order."**

### Sample 1: a stop flag that may never stop

```csharp
var worker = new Thread(Worker.Run) { IsBackground = true };   // background: lets the app exit anyway
worker.Start();

Thread.Sleep(500);
Worker.Stop = true;   // ask the worker to stop
Console.WriteLine("Main: stop requested");

Console.WriteLine(worker.Join(2000)
    ? "Main: worker stopped"
    : "Main: worker is STILL running: it never saw Stop = true");

static class Worker
{
    public static bool Stop;   // ❌ plain field: the loop below may read it only once

    public static void Run()
    {
        long loops = 0;
        while (!Stop)          // the JIT may turn this into: if (!Stop) while (true) ...
        {
            loops++;
        }
        Console.WriteLine($"Worker: stopped after {loops} loops");
    }
}
```

In a Release build, the JIT sees that nothing *inside the loop* changes `Stop`, so it may read it **once** and loop forever. Whether it actually hangs depends on the .NET version, the CPU and JIT tiering. The point is that it's **allowed** to.

### Sample 2: the fix with `volatile`

Only the field declaration changes:

```csharp
var worker = new Thread(Worker.Run) { IsBackground = true };
worker.Start();

Thread.Sleep(500);
Worker.Stop = true;
Console.WriteLine("Main: stop requested");

Console.WriteLine(worker.Join(2000)
    ? "Main: worker stopped"
    : "Main: worker is STILL running");

static class Worker
{
    public static volatile bool Stop;   // ✅ every read goes to memory: the loop sees the change

    public static void Run()
    {
        long loops = 0;
        while (!Stop)
        {
            loops++;
        }
        Console.WriteLine($"Worker: stopped after {loops} loops");
    }
}
```

### Sample 3: publishing data safely (ordering)

`volatile` also controls **ordering**:

- A **volatile write** has *release* semantics: earlier writes can't be moved *after* it.
- A **volatile read** has *acquire* semantics: later reads can't be moved *before* it.

(What these terms mean is explained right after this sample, in [Acquire and release semantics](#acquire-and-release-semantics).)

That makes the "prepare data, then raise a flag" pattern safe:

```csharp
var writer = new Thread(Shared.Publish);
var reader = new Thread(Shared.Consume);
reader.Start();
writer.Start();
writer.Join();
reader.Join();

static class Shared
{
    static int _data;               // ordinary field
    static volatile bool _ready;    // the flag that "publishes" _data

    public static void Publish()
    {
        _data = 42;                 // 1. prepare the data
        _ready = true;              // 2. volatile write: _data = 42 can't move after this
    }

    public static void Consume()
    {
        while (!_ready) { }         // volatile read: wait until published
        Console.WriteLine(_data);   // guaranteed to print 42, never 0
    }
}
```

Without `volatile` on `_ready`, the two writes (or the two reads) could be reordered, and the reader could see `_ready == true` but still read the old `_data == 0`. This is more likely on CPUs with weaker ordering rules, such as **ARM** (including Apple Silicon).

### Acquire and release semantics

The names come from **locks**: a thread *acquires* a lock to enter a critical section and *releases* it when leaving. A volatile write and a volatile read give the same ordering guarantees as a lock's exit and entry, but for a single field.

**Release (a volatile write): "publish everything done before this"**

```
_data = 42;        ─┐
_name = "Alice";    │  these must stay ABOVE the line
────────────────────┴────────
_ready = true;     ← volatile write (release)
```

Writes before the release can't be moved *after* it. By the time another thread can see `_ready == true`, the earlier writes are complete too. Like **sealing an envelope**: everything put in before sealing is inside.

**Acquire (a volatile read): "receive everything published before this"**

```
if (_ready)        ← volatile read (acquire)
────────────────────┬────────
    use(_data);     │  these must stay BELOW the line
    use(_name);    ─┘
```

Reads after the acquire can't be moved *before* it, so nothing is read early and stale. Like **opening the envelope**: the contents are read only after opening it.

**Together: a handover between threads**

```
Thread A (writer)                    Thread B (reader)
─────────────────                    ─────────────────
_data = 42;
_name = "Alice";
_ready = true;   ── release ──────▶  if (_ready)        ── acquire
                                         read _data  → 42
                                         read _name  → "Alice"
```

If B's acquire read sees the value written by A's release write, then **B is guaranteed to see everything A wrote before that release**. This is called a *happens-before* relationship.

**Why "one-way" barriers?**

| | Blocked | Still allowed |
|---|---|---|
| **Release** (write) | Earlier operations moving **down past** it | Later operations moving **up before** it |
| **Acquire** (read) | Later operations moving **up past** it | Earlier operations moving **down after** it |

The allowed direction always moves code *into* the protected region, never *out* of it, so it's harmless. Leaving that freedom lets the CPU and JIT optimise more than a full two-way barrier (a *full fence*, e.g. `Interlocked` operations or `Thread.MemoryBarrier()`), so it's cheaper.

### Sample 4: `volatile` does NOT make `++` atomic

```csharp
var a = new Thread(Counter.Increment);
var b = new Thread(Counter.Increment);
a.Start(); b.Start();
a.Join();  b.Join();

Console.WriteLine($"Count: {Counter.Count}");   // expected 200000, usually less!

static class Counter
{
    public static volatile int Count;

    public static void Increment()
    {
        for (int i = 0; i < 100_000; i++)
            Count++;   // ❌ still read → add → write: two threads can interleave and lose updates
    }
}
```

`volatile` makes each **read** and each **write** see fresh values, but `Count++` is *three* steps. Another thread can slip in between them. The fix is an atomic operation (or a `lock`):

```csharp
var a = new Thread(Counter.Increment);
var b = new Thread(Counter.Increment);
a.Start(); b.Start();
a.Join();  b.Join();

Console.WriteLine($"Count: {Counter.Count}");   // always 200000

static class Counter
{
    public static int Count;   // no volatile needed: Interlocked handles visibility too

    public static void Increment()
    {
        for (int i = 0; i < 100_000; i++)
            Interlocked.Increment(ref Count);   // ✅ one atomic step
    }
}
```

### What `volatile` does and doesn't do

| ✅ Does | ❌ Doesn't |
|---|---|
| Forces each read to get the latest value (no caching in registers) | Make compound operations (`++`, `+=`, check-then-set) atomic |
| Gives writes *release* and reads *acquire* ordering | Prevent a volatile write followed by a volatile read from being reordered |
| Works for simple flags and "publish once" patterns | Replace a `lock` when several fields must change together |

### Rules

- Only on **fields**, not local variables or parameters.
- Can't be combined with `readonly` (a `readonly` field never changes after construction, so it doesn't need it).
- Only for types that can be read/written atomically:

| Allowed | Not allowed |
|---|---|
| Reference types | `long`, `ulong` |
| `bool`, `char`, `byte`, `sbyte`, `short`, `ushort`, `int`, `uint`, `float` | `double`, `decimal` |
| `nint`, `nuint` (`IntPtr`, `UIntPtr`), pointers | Structs (other than the ones listed) |
| Enums based on the allowed integer types | |

For `long` or `double`, use the `Volatile` class or `Interlocked` instead (next section).

### Alternatives

**`Volatile.Read` / `Volatile.Write`**: the same guarantees, but per access instead of per field. They also work with `long` and `double`, and make the special access visible at the call site.

```csharp
var worker = new Thread(Worker.Run) { IsBackground = true };
worker.Start();

Thread.Sleep(500);
Volatile.Write(ref Worker.Stop, true);   // ✅ volatile write
worker.Join();

static class Worker
{
    public static bool Stop;   // plain field

    public static void Run()
    {
        while (!Volatile.Read(ref Worker.Stop)) { }   // ✅ volatile read
        Console.WriteLine("Worker: stopped");
    }
}
```

**`CancellationToken`**: the idiomatic way to ask work to stop. It handles visibility for you, and also works with `Task`, `async` methods and timeouts.

```csharp
using var cts = new CancellationTokenSource();

var worker = new Thread(() => Run(cts.Token));
worker.Start();

Thread.Sleep(500);
cts.Cancel();   // ✅ request the stop
worker.Join();

static void Run(CancellationToken token)
{
    long loops = 0;
    while (!token.IsCancellationRequested)
    {
        loops++;
    }
    Console.WriteLine($"Worker: stopped after {loops} loops");
}
```

**Which to choose:**

| Need | Use |
|---|---|
| Ask a thread or task to stop | `CancellationToken` |
| Increment / add / swap a single number | `Interlocked` |
| Update several fields together, or check-then-act | `lock` |
| Create something once, lazily, thread-safely | `Lazy<T>` (see [Lazy](Lazy.md)) |
| A simple flag or published reference in low-level code | `volatile` or `Volatile.Read/Write` |

### `volatile` in other languages

The same keyword means different things elsewhere:

| Language | What `volatile` means |
|---|---|
| **C#** | Acquire/release ordering + no caching of the field |
| **Java** | Stronger: also no reordering of a volatile write followed by a volatile read (sequentially consistent) |
| **C / C++** | **Not** for threading: it's for memory-mapped hardware. Use `std::atomic` for threads |

### Key points

- `volatile` is about **visibility and ordering**, not **atomicity**.
- It fixes "a thread never sees the update" and "a thread sees the flag before the data".
- It does **not** make `++`, `+=` or check-then-act safe: use `Interlocked` or `lock`.
- Only on fields, only for small types (no `long`/`double`); `Volatile.Read/Write` covers the rest.
- Bugs it prevents usually appear only in **Release builds** and on some CPUs, which makes them hard to reproduce.
- In modern code, prefer **`CancellationToken`, `Interlocked`, `lock` and `Lazy<T>`**. Reach for `volatile` only in low-level code where you can explain exactly why it's enough.

### Think about it

In Sample 3, suppose the writer also set a second field, `_name = "Alice"`, *after* `_ready = true`. Is the reader guaranteed to see `"Alice"`? Why or why not?
