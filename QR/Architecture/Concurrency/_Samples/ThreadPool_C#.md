## Concurrency
### ThreadPool in C# (Basics)

#### [Back to Concurrency contents](../_Contents.md)

*Is there a `ThreadPool` keyword in C#?*

Short answer: **No. `ThreadPool` is not a keyword; it's a static class (`System.Threading.ThreadPool`). It manages a set of reusable worker threads. You rarely call it directly, because `Task.Run`, `async/await` and `Parallel` use it for you.**

> Each sample is a complete console program (top-level statements, .NET 6+). Paste one into `Program.cs` and run it with `dotnet run`.

### Keyword vs class

- **Keywords** are part of the C# language itself. The concurrency-related ones are `async`, `await` and `lock` (plus the rarely used `volatile`).
- **`ThreadPool`** is a .NET library class, like `Thread` or `Task`. You call methods on it.

### What the thread pool is

Creating a thread is expensive (about 1 MB of stack, plus the time to create it). The thread pool keeps a set of **worker threads alive and reuses them**:

```
Your code ──queue work──▶ [ work queue ] ──▶ free pool thread runs it ──▶ thread goes back to the pool
```

The runtime decides how many threads to keep. It starts with about one per CPU core and adds more gradually when work is waiting.

### 1. Queue work directly: `QueueUserWorkItem`

```csharp
using System.Threading;

using var done = new CountdownEvent(3);   // "wait until 3 signals arrive"

for (int i = 1; i <= 3; i++)
{
    ThreadPool.QueueUserWorkItem(state =>
    {
        Console.WriteLine($"Item {state} on thread {Environment.CurrentManagedThreadId}, " +
                          $"pool thread? {Thread.CurrentThread.IsThreadPoolThread}");
        done.Signal();
    }, i);   // the second argument is passed in as 'state'
}

done.Wait();   // there is no Join() or await for queued work, so signal it yourself
Console.WriteLine("All items done");
```

`QueueUserWorkItem` is "fire and forget". You get **no** handle to wait on, no return value and no exception handling. That's why `Task.Run` replaced it in everyday code.

### 2. Who runs on the pool?

```csharp
using System.Threading;

// Thread you create yourself: NOT a pool thread
var t = new Thread(() => Report("new Thread"));
t.Start();
t.Join();

// Task.Run: pool thread
await Task.Run(() => Report("Task.Run"));

// Parallel.For: pool threads (and the calling thread)
Parallel.For(0, 2, i => Report($"Parallel.For #{i}"));

// LongRunning task: gets a dedicated thread, NOT from the pool
await Task.Factory.StartNew(() => Report("LongRunning task"), TaskCreationOptions.LongRunning);

static void Report(string label) =>
    Console.WriteLine($"{label,-20} thread {Environment.CurrentManagedThreadId,3}  " +
                      $"pool? {Thread.CurrentThread.IsThreadPoolThread}");
```

Things that use the pool behind the scenes:

- `Task.Run(...)`
- `async/await` continuations (in apps without a UI thread)
- `Parallel.For` / `Parallel.ForEach` / PLINQ
- `System.Threading.Timer` callbacks

#### `new Thread` does NOT use the pool

- `Start()` asks the OS to create a **brand-new, dedicated thread** just for your method.
- When your method returns, that thread **ends and is destroyed**. It doesn't go back anywhere to be reused.
- `IsThreadPoolThread` is `false` for it (the `new Thread` line above prints `pool? False`).

```
new Thread:   create OS thread → run your method → thread dies
Task.Run:     borrow pool thread → run your work → thread returns to the pool for the next item
```

> **The twist:** pool threads are ordinary threads too. The runtime creates them itself and keeps them alive in a loop, waiting for queued work. So the pool is built from threads, but threads you create yourself never join it.

A dedicated thread isn't shared, so it's a good fit when:

- the work runs for a **long time or forever** (a background loop, a queue consumer) and would otherwise tie up a pool thread
- the work **blocks** a lot, so it can't starve the pool
- you need **thread-specific settings**: `IsBackground`, `Priority`, a name, or an STA apartment for COM/UI work

`TaskCreationOptions.LongRunning` gets you the same thing, a dedicated non-pool thread, while still giving you a `Task` you can `await`.

### 3. Threads are reused

```csharp
var ids = new List<int>();

for (int i = 0; i < 20; i++)
{
    await Task.Run(() => ids.Add(Environment.CurrentManagedThreadId));   // one at a time
}

Console.WriteLine($"20 tasks ran on {ids.Distinct().Count()} distinct thread(s): " +
                  string.Join(", ", ids.Distinct()));
```

20 tasks ran, but only a few distinct thread IDs appear. The same pool threads were reused.

### 4. Pool statistics

```csharp
using System.Threading;

ThreadPool.GetMinThreads(out int minWorker, out int minIo);
ThreadPool.GetMaxThreads(out int maxWorker, out int maxIo);

Console.WriteLine($"CPU cores:          {Environment.ProcessorCount}");
Console.WriteLine($"Min worker threads: {minWorker}");   // usually = number of cores
Console.WriteLine($"Max worker threads: {maxWorker}");
Console.WriteLine($"Threads right now:  {ThreadPool.ThreadCount}");
Console.WriteLine($"Pending work items: {ThreadPool.PendingWorkItemCount}");
Console.WriteLine($"Completed items:    {ThreadPool.CompletedWorkItemCount}");
```

| Member | What it does |
|---|---|
| `GetMinThreads` / `SetMinThreads` | Threads the pool creates **immediately** on demand; beyond this, it adds them slowly |
| `GetMaxThreads` / `SetMaxThreads` | Upper limit on pool threads |
| `ThreadCount` | Pool threads that exist right now |
| `PendingWorkItemCount` | Work waiting in the queue |
| `CompletedWorkItemCount` | Work items finished so far |

### 5. Thread-pool starvation

Pool threads are shared. If work **blocks** them (`Thread.Sleep`, `.Result`, `.Wait()`, synchronous I/O), queued work has to wait for a free thread. The pool adds threads only slowly, so everything gets slower.

```csharp
using System.Diagnostics;

int n = Environment.ProcessorCount * 4;   // more work items than starting threads

// ❌ Blocking: each item holds a pool thread for 1 s
var sw = Stopwatch.StartNew();
await Task.WhenAll(Enumerable.Range(0, n).Select(_ =>
    Task.Run(() => Thread.Sleep(1000))));
Console.WriteLine($"Blocking:     {sw.ElapsedMilliseconds} ms");   // noticeably more than 1000

// ✅ Non-blocking: the thread is freed during the wait
sw.Restart();
await Task.WhenAll(Enumerable.Range(0, n).Select(_ =>
    Task.Run(async () => await Task.Delay(1000))));
Console.WriteLine($"Non-blocking: {sw.ElapsedMilliseconds} ms");   // about 1000
```

The exact numbers depend on your machine. The blocking version is clearly slower because only about one thread per core starts right away, and the rest of the work queues up behind them.

How to avoid starvation:

- Use `await` instead of `.Result` / `.Wait()`.
- Use `await Task.Delay` instead of `Thread.Sleep` in async code.
- For long-running blocking work, use a dedicated thread (`new Thread` or `TaskCreationOptions.LongRunning`) instead of borrowing a pool thread.
- `SetMinThreads` can hide the symptom, but it doesn't fix the blocking.

### Quick reference

| Member | What it does |
|---|---|
| `ThreadPool.QueueUserWorkItem(cb, state)` | Queue fire-and-forget work for a pool thread |
| `Thread.CurrentThread.IsThreadPoolThread` | Is this code running on a pool thread? |
| `ThreadPool.ThreadCount` | Current number of pool threads |
| `ThreadPool.PendingWorkItemCount` | Work waiting in the queue |
| `ThreadPool.GetMinThreads` / `SetMinThreads` | How quickly the pool adds threads |
| `TaskCreationOptions.LongRunning` | Ask for a dedicated thread instead of a pool thread |

### Thread vs ThreadPool vs Task

| | `new Thread` | `ThreadPool.QueueUserWorkItem` | `Task.Run` |
|---|---|---|---|
| Thread source | New, dedicated | Shared pool | Shared pool |
| Creation cost | High | Low (reuse) | Low (reuse) |
| Wait for it | `Join()` | ❌ (signal yourself) | `await` |
| Return value | ❌ | ❌ | ✅ `Task<T>` |
| Exceptions | Crash the process if unhandled | Crash the process if unhandled | Stored and rethrown on `await` |
| Cancellation | Do it yourself | Do it yourself | `CancellationToken` |
| Use for | Long-running / special settings | Rarely, in modern code | Everyday CPU-bound work |

See [Thread (C#)](Thread_C%23.md) and [Task (C#)](Task_C%23.md).
