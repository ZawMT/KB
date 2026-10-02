## Concurrency
### Tasks in C# (Basics)

#### [Back to Concurrency contents](../_Contents.md)

*How do you run work with a `Task` in C#?*

Short answer: **A `Task` is a unit of work that the runtime schedules for you, usually on the thread pool. Start CPU-bound work with `Task.Run(...)`, write I/O-bound work as `async` methods, and use `await` to get the result without blocking.**

> Each sample is a complete console program (top-level statements, .NET 6+). Paste one into `Program.cs` and run it with `dotnet run`. Top-level code can use `await` directly.

### 1. Run work on the thread pool and await it

```csharp
Console.WriteLine($"Main on thread {Environment.CurrentManagedThreadId}");

Task work = Task.Run(() =>
{
    Console.WriteLine($"Work on thread {Environment.CurrentManagedThreadId}");
    Thread.Sleep(1000);   // simulate CPU work (blocks this pool thread)
});

Console.WriteLine("Main: doing other things...");

await work;               // wait without blocking: like Join(), but async
Console.WriteLine("Main: work finished");
```

Compared to a `Thread`, you don't create or start anything. `Task.Run` borrows a thread from the **thread pool** and returns it when the work is done.

### 2. Returning a result: `Task<T>`

```csharp
Task<int> sumTask = Task.Run(() =>
{
    int sum = 0;
    for (int i = 1; i <= 100; i++) sum += i;
    return sum;
});

int result = await sumTask;   // await unwraps the value
Console.WriteLine($"Sum: {result}");   // Sum: 5050
```

A `Task<T>` is also a **future**, meaning a handle to a result that will be ready later.

### 3. Writing your own `async` method

```csharp
Console.WriteLine("Fetching...");
string data = await FetchDataAsync("users");
Console.WriteLine(data);

static async Task<string> FetchDataAsync(string name)
{
    await Task.Delay(1000);   // simulate a network call: NO thread is blocked
    return $"Data for '{name}'";
}
```

- `async` lets the method use `await`.
- The return type is `Task` (no result) or `Task<T>` (with a result).
- By convention, async method names end with `Async`.
- `Task.Delay` is the async version of `Thread.Sleep`. It frees the thread while waiting.

### 4. Run several tasks at once: `Task.WhenAll`

```csharp
using System.Diagnostics;

var sw = Stopwatch.StartNew();

Task<string> a = FetchDataAsync("users", 1000);
Task<string> b = FetchDataAsync("orders", 1000);
Task<string> c = FetchDataAsync("products", 1000);

string[] results = await Task.WhenAll(a, b, c);   // wait for all of them

foreach (var r in results) Console.WriteLine(r);
Console.WriteLine($"Took ~{sw.ElapsedMilliseconds} ms");   // ~1000, not ~3000

static async Task<string> FetchDataAsync(string name, int ms)
{
    await Task.Delay(ms);
    return $"Data for '{name}'";
}
```

The tasks start running as soon as `FetchDataAsync` is called. `WhenAll` just waits for all of them.

> ❌ Awaiting one at a time (`await FetchDataAsync(...)` three times in a row) runs them **sequentially** and takes about 3 seconds.

### 5. First one wins: `Task.WhenAny`

```csharp
Task<string> fast = FetchDataAsync("fast server", 500);
Task<string> slow = FetchDataAsync("slow server", 2000);

Task<string> winner = await Task.WhenAny(fast, slow);
Console.WriteLine($"Winner: {await winner}");   // Winner: Data for 'fast server'

static async Task<string> FetchDataAsync(string name, int ms)
{
    await Task.Delay(ms);
    return $"Data for '{name}'";
}
```

`WhenAny` returns the **task** that finished first. You still `await` it to get its value (or its exception).

### 6. Exceptions

```csharp
try
{
    await FailAsync();
}
catch (InvalidOperationException ex)
{
    Console.WriteLine($"Caught: {ex.Message}");
}

static async Task FailAsync()
{
    await Task.Delay(500);
    throw new InvalidOperationException("Something went wrong");
}
```

An exception inside a task is **stored in the task** and rethrown when you `await` it, so a normal `try/catch` works.

> With `Task.WhenAll`, `await` rethrows only the **first** exception. The rest are in the `Exception` property of the task that `WhenAll` returned (an `AggregateException`).

### 7. Cancellation

```csharp
using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(2));   // auto-cancel after 2 s

try
{
    await CountAsync(cts.Token);
}
catch (OperationCanceledException)
{
    Console.WriteLine("Cancelled!");
}

static async Task CountAsync(CancellationToken token)
{
    for (int i = 1; ; i++)
    {
        Console.WriteLine($"Count {i}");
        await Task.Delay(500, token);   // throws when the token is cancelled
    }
}
```

- `CancellationTokenSource` **requests** cancellation (`cts.Cancel()`, or a timeout).
- `CancellationToken` is passed into the work, which **checks** it.
- Cancellation is **cooperative**: the task stops only if it checks the token (or passes it to methods that do).
  - In a CPU loop, call `token.ThrowIfCancellationRequested();` yourself.

### 8. Don't block on tasks

```csharp
// ❌ Blocks the current thread until the task finishes
string a = FetchDataAsync("users").Result;
FetchDataAsync("users").Wait();

// ✅ Frees the thread while waiting
string b = await FetchDataAsync("users");

static async Task<string> FetchDataAsync(string name)
{
    await Task.Delay(1000);
    return $"Data for '{name}'";
}
```

`.Result` and `.Wait()` tie up a thread and can **deadlock** on a UI thread (WinForms/WPF). Use `await` all the way up the call chain ("async all the way").

### Quick reference

| Member | What it does |
|---|---|
| `Task.Run(() => ...)` | Run work on a thread-pool thread |
| `await task` | Wait without blocking; get the result or rethrow the exception |
| `Task<T>` | A task that produces a value of type `T` |
| `async Task` / `async Task<T>` | Declare a method that can use `await` |
| `Task.Delay(ms)` | Async wait (no thread blocked) |
| `Task.WhenAll(...)` | Wait for all tasks |
| `Task.WhenAny(...)` | Wait for the first task to finish |
| `CancellationTokenSource` / `CancellationToken` | Request / observe cancellation |
| `Task.CompletedTask` / `Task.FromResult(x)` | An already-finished task |

### Task vs Thread

| | `Thread` | `Task` |
|---|---|---|
| What it is | Unit of **execution** | Unit of **work** |
| Created by | You (`new Thread`) | The runtime / thread pool |
| Returns a value | ❌ (you share variables) | ✅ `Task<T>` |
| Exceptions | Crash the process if unhandled | Stored and rethrown on `await` |
| Cancellation | Do it yourself | Built in (`CancellationToken`) |
| Waiting | `Join()` (blocks) | `await` (doesn't block) |

See [Thread (C#)](Thread_C%23.md) for the low-level version, and [Entities](../_Notes/Concurrency_03_Entities.md) for why no thread is used while awaiting I/O.
