## Concurrency
### Threads in C# (Basics)

#### [Back to Concurrency contents](../_Contents.md)

*How do you create and run a thread in C#?*

Short answer: **Create a `System.Threading.Thread`, give it a method to run, call `Start()`, and call `Join()` when you need to wait for it to finish.**

> Each sample is a complete console program (top-level statements, .NET 6+). Paste one into `Program.cs` and run it with `dotnet run`.

### 1. Create, start and wait

```csharp
using System.Threading;

Thread worker = new Thread(DoWork);   // 1. Create: give it the method to run
worker.Start();                       // 2. Start: it now runs alongside Main

Console.WriteLine("Main: doing other things...");

worker.Join();                        // 3. Join: wait here until the worker finishes
Console.WriteLine("Main: worker finished");

static void DoWork()
{
    for (int i = 1; i <= 3; i++)
    {
        Console.WriteLine($"Worker: step {i}");
        Thread.Sleep(500);            // pause this thread for 500 ms
    }
}
```

Possible output (the order between Main and Worker can vary from run to run):

```
Main: doing other things...
Worker: step 1
Worker: step 2
Worker: step 3
Main: worker finished
```

### 2. Passing data with a lambda

The easiest way to pass parameters is to capture them in a lambda.

```csharp
using System.Threading;

string name = "Alice";
int count = 3;

Thread t = new Thread(() => Greet(name, count));
t.Start();
t.Join();

static void Greet(string name, int times)
{
    for (int i = 0; i < times; i++)
        Console.WriteLine($"Hello, {name}!");
}
```

> ⚠️ Lambdas capture **variables**, not values. Starting threads inside a loop with `() => Work(i)` (where `i` is a `for` loop variable) can show the same `i` in several threads. Copy it to a local first: `int copy = i;` then `() => Work(copy)`.

### 3. Thread identity: name and ID

```csharp
using System.Threading;

Console.WriteLine($"Main thread ID: {Environment.CurrentManagedThreadId}");

Thread t = new Thread(() =>
{
    Console.WriteLine($"Running on: {Thread.CurrentThread.Name}, " +
                      $"ID: {Environment.CurrentManagedThreadId}");
});
t.Name = "MyWorker";   // handy when debugging
t.Start();
t.Join();
```

### 4. Multiple threads at once

```csharp
using System.Threading;

var threads = new List<Thread>();

for (int i = 1; i <= 3; i++)
{
    int id = i;   // copy the loop variable (see the warning above)
    var t = new Thread(() =>
    {
        Console.WriteLine($"Thread {id} started");
        Thread.Sleep(1000);
        Console.WriteLine($"Thread {id} done");
    });
    threads.Add(t);
    t.Start();
}

foreach (var t in threads)
    t.Join();   // wait for all of them

Console.WriteLine("All threads finished");
```

All three threads sleep **at the same time**, so the program takes about 1 second, not 3.

### 5. Foreground vs background threads

```csharp
using System.Threading;

Thread t = new Thread(() =>
{
    Thread.Sleep(3000);
    Console.WriteLine("Background thread done");   // never printed
});
t.IsBackground = true;   // default is false (foreground)
t.Start();

Console.WriteLine("Main exiting");
// No Join(): the process exits without waiting for the background thread
```

| Type | Keeps the process alive? |
|---|---|
| **Foreground** (default) | ✅ The process waits for it to finish |
| **Background** (`IsBackground = true`) | ❌ Killed when all foreground threads end |

### 6. Shared data: race condition and `lock`

Threads share memory, so two threads updating the same variable can corrupt it.

```csharp
using System.Threading;

int counter = 0;

Thread a = new Thread(Increment);
Thread b = new Thread(Increment);
a.Start(); b.Start();
a.Join();  b.Join();

Console.WriteLine($"Counter: {counter}");   // expected 200000, usually less!

void Increment()
{
    for (int i = 0; i < 100_000; i++)
        counter++;   // ❌ not atomic: read → add → write can interleave
}
```

Fix it with `lock`, so only one thread at a time can enter the block:

```csharp
using System.Threading;

int counter = 0;
object gate = new object();

Thread a = new Thread(Increment);
Thread b = new Thread(Increment);
a.Start(); b.Start();
a.Join();  b.Join();

Console.WriteLine($"Counter: {counter}");   // always 200000

void Increment()
{
    for (int i = 0; i < 100_000; i++)
    {
        lock (gate)
        {
            counter++;
        }
    }
}
```

> For a simple counter, `Interlocked.Increment(ref counter);` is a lighter alternative to `lock`.

### 7. `Thread.Sleep` blocks the current thread

`Thread.Sleep` blocks only the **thread that calls it**, not the whole program.

- It tells the OS "don't schedule me for X ms."
- It uses **no CPU** while sleeping, but the thread is **occupied** and can't do anything else.
- **Other threads keep running.** In sample 4, three threads sleep for 1 second at the same time, and the whole program takes about 1 second.

When blocking hurts:

| Where it's called | Effect |
|---|---|
| A dedicated `Thread` you created | Usually fine; only that thread waits |
| The **UI thread** (WinForms/WPF) | The app **freezes**: no clicks, no redraws |
| Inside an `async` method or `Task.Run` | Wastes a **thread-pool thread**; doing it a lot can starve the pool, so other tasks wait for a free thread |
| Inside a `lock` block | The lock stays held while sleeping, so other threads waiting on it are blocked too |

The non-blocking alternative is `Task.Delay`:

```csharp
Thread.Sleep(1000);        // ❌ the thread sits idle for 1 s
await Task.Delay(1000);    // ✅ the thread is freed; a timer resumes the method later
```

`Task.Delay` uses a timer, so **no thread is tied up during the wait** (same idea as awaiting a network call in [Entities](../_Notes/Concurrency_03_Entities.md)).

**Rule of thumb:**

- `Thread.Sleep` is fine in plain thread code, quick demos and tests.
- In `async` code, always use `await Task.Delay`.

> In [Task (C#)](Task_C%23.md) sample 1, `Thread.Sleep` inside `Task.Run` stands in for CPU work on purpose, so it does block a pool thread. To simulate *waiting* (samples 3–7 there), `Task.Delay` is the right choice.

### Quick reference

| Member | What it does |
|---|---|
| `new Thread(method)` | Create a thread (not running yet) |
| `Start()` | Begin running it |
| `Join()` | Block the caller until the thread finishes |
| `Thread.Sleep(ms)` | Pause (block) the **current** thread; use `await Task.Delay(ms)` in async code |
| `IsBackground` | `true` = doesn't keep the process alive |
| `Name` | Label for debugging |
| `Thread.CurrentThread` | The thread running this code |
| `lock (obj) { ... }` | Let one thread at a time into a block |

### In real code

Creating a `Thread` by hand is the **low-level** way. Each thread costs about 1 MB of stack and takes time to create. In modern C# you'll usually use:

- `Task.Run(...)` for CPU-bound work. It runs on the **thread pool**, which reuses threads.
- `async/await` for I/O-bound work. No thread is used during the wait (see [Entities](../_Notes/Concurrency_03_Entities.md)).

A dedicated `Thread` still makes sense for **long-running** work that would hog a pool thread, or when you need control over thread settings like `IsBackground` or `Priority`.
