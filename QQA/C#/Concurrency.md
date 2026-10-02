# Q: [Thread accepts what kind of methods?](#a-thread-accepts-what-kind-of-methods)
# Q: [What is the difference between Thread.Sleep and Task.Delay?](#a-what-is-the-difference-between-threadsleep-and-taskdelay)

# A: Thread accepts what kind of methods?

[Back to questions](#q-thread-accepts-what-kind-of-methods)

A `System.Threading.Thread` is created by giving its constructor a method to run. That method must match one of **two** signatures, and neither can return a value.

### What it's called

The method a thread runs is called its **entry point**, or *thread start method*. The constructor parameter that receives it is a **delegate**: C#'s type for "a reference to a method with a specific signature". `Thread` defines two of them:

| Delegate | Signature | Meaning |
|---|---|---|
| `ThreadStart` | `void ()` | No parameters, no return value |
| `ParameterizedThreadStart` | `void (object?)` | One `object` parameter, no return value |

So the constructors are really:

```csharp
new Thread(ThreadStart start)
new Thread(ParameterizedThreadStart start)
```

### Shape 1: `void ()`

```csharp
Thread t = new Thread(DoWork);   // the method name is converted to a ThreadStart
t.Start();
t.Join();

static void DoWork() => Console.WriteLine("Working");
```

### Shape 2: `void (object?)`, passing a value through `Start`

```csharp
Thread t = new Thread(Greet);
t.Start("Alice");   // this value becomes the 'state' parameter
t.Join();

static void Greet(object? state)
{
    string name = (string)state!;   // must cast: it arrives as object
    Console.WriteLine($"Hello, {name}");
}
```

This is the older style. It's clumsy because everything arrives as `object`, so you have to cast it back, and the compiler can't check the type for you.

### The usual way around both limits: a lambda

A lambda like `() => ...` **is** a `void ()` method, but it can call *any* method you like, with any parameters:

```csharp
Thread t = new Thread(() => Greet("Alice", 3));   // fully typed, any number of parameters
t.Start();
t.Join();

static void Greet(string name, int times)
{
    for (int i = 0; i < times; i++) Console.WriteLine($"Hello, {name}");
}
```

This is the most common style in modern C# code.

### Getting a result back

Neither delegate returns a value, so with a `Thread` you write the result into a **shared variable** and read it after `Join()`:

```csharp
int result = 0;
Thread t = new Thread(() => result = Sum(1, 100));
t.Start();
t.Join();   // after Join, it's safe to read 'result'
Console.WriteLine(result);   // 5050

static int Sum(int from, int to) => Enumerable.Range(from, to - from + 1).Sum();
```

This limitation is one of the reasons `Task<T>` exists. With `int result = await Task.Run(() => Sum(1, 100));`, the value comes back directly.

### Summary

| You want | Use |
|---|---|
| No input | `new Thread(Method)` with `void Method()` |
| One input, old style | `new Thread(Method)` + `Start(value)` with `void Method(object?)` |
| Any inputs, typed | `new Thread(() => Method(a, b, c))` |
| A return value | Shared variable + `Join()`, or better, `Task<T>` |

### Try it

What happens at **compile time** if a method that returns a value, such as `static int Sum(int from, int to)`, is passed directly with `new Thread(Sum)`? The error message names exactly which delegate signatures were expected.

# A: What is the difference between Thread.Sleep and Task.Delay?

[Back to questions](#q-what-is-the-difference-between-threadsleep-and-threaddelay)

Short answer: **`Thread.Sleep` blocks the current thread for the given time; the thread can do nothing else. `await Task.Delay` waits *without* blocking: the thread is freed, and a timer resumes the method later.**

### Comparison

| | `Thread.Sleep(ms)` | `await Task.Delay(ms)` |
|---|---|---|
| Kind | Synchronous (blocking) | Asynchronous (non-blocking) |
| Thread during the wait | **Occupied**, doing nothing | **Free** to do other work |
| How it waits | The OS stops scheduling the thread | A timer completes a `Task` |
| Returns | `void` | `Task` (must be `await`ed) |
| Used in | Plain synchronous code | `async` methods |
| Cancellable | ❌ | ✅ with a `CancellationToken` |
| Thread after the wait | Always the same thread | May be a different thread |

### Sample 1: `Thread.Sleep` blocks the thread

```csharp
Console.WriteLine($"Before: thread {Environment.CurrentManagedThreadId}");

Thread.Sleep(1000);   // this thread is stuck here for 1 second

Console.WriteLine($"After:  thread {Environment.CurrentManagedThreadId}");   // same thread
```

The thread uses no CPU while sleeping, but it can't run anything else either.

### Sample 2: `Task.Delay` frees the thread

```csharp
Console.WriteLine($"Before: thread {Environment.CurrentManagedThreadId}");

await Task.Delay(1000);   // the thread is released; a timer resumes this code later

Console.WriteLine($"After:  thread {Environment.CurrentManagedThreadId}");   // often a different thread
```

In a console app, the code after `await` usually continues on a thread-pool thread, so the ID may change. In a UI app it returns to the UI thread.

### Sample 3: many waits at once

Both versions below wait 1 second, 50 times, concurrently.

```csharp
using System.Diagnostics;

const int count = 50;

// ❌ Thread.Sleep: each wait holds a thread-pool thread for the whole second
var sw = Stopwatch.StartNew();
await Task.WhenAll(Enumerable.Range(0, count).Select(_ =>
    Task.Run(() => Thread.Sleep(1000))));
Console.WriteLine($"Thread.Sleep: {sw.ElapsedMilliseconds} ms, pool threads: {ThreadPool.ThreadCount}");

// ✅ Task.Delay: no thread is held during the wait
sw.Restart();
await Task.WhenAll(Enumerable.Range(0, count).Select(_ =>
    Task.Delay(1000)));
Console.WriteLine($"Task.Delay:   {sw.ElapsedMilliseconds} ms");
```

- `Thread.Sleep` version: takes **several seconds**. The pool starts with about one thread per CPU core and adds more slowly, so the waits queue up behind each other (*thread-pool starvation*).
- `Task.Delay` version: about **1 second**. All 50 waits are just timers; no threads are tied up.

Exact numbers depend on the machine.

### Sample 4: cancelling a wait

`Task.Delay` can be cancelled part-way; `Thread.Sleep` always runs to the end.

```csharp
using var cts = new CancellationTokenSource();
cts.CancelAfter(500);   // cancel after 0.5 s

try
{
    await Task.Delay(5000, cts.Token);   // would wait 5 s, but is cancelled at 0.5 s
    Console.WriteLine("Finished waiting");
}
catch (TaskCanceledException)
{
    Console.WriteLine("Wait was cancelled");
}
```

### Common mistakes

```csharp
// ❌ Missing await: creates the delay task and ignores it, so there is NO pause
Task.Delay(1000);

// ❌ Blocking on Task.Delay: same effect as Thread.Sleep (the thread is stuck)
Task.Delay(1000).Wait();

// ❌ Thread.Sleep inside an async method: blocks a thread the async code was meant to free
async Task BadAsync()
{
    Thread.Sleep(1000);
    await Task.CompletedTask;
}

// ✅ Correct inside async code
async Task GoodAsync()
{
    await Task.Delay(1000);
}
```

Inside an `async` method, the compiler warns about the first one (CS4014: "Because this call is not awaited..."). Don't ignore that warning.

### Where blocking hurts

| Thread that sleeps | Effect of `Thread.Sleep` |
|---|---|
| A dedicated thread created with `new Thread` | Usually fine: only that thread waits |
| The UI thread (WinForms, WPF, MAUI) | The app **freezes**: no clicks, no redraws |
| A thread-pool thread (`Task.Run`, ASP.NET requests) | Fewer threads available for other work; can cause starvation |
| A thread holding a `lock` | The lock stays held, so other threads waiting for it are blocked too |

### When to use which

- **`Thread.Sleep`**: simple synchronous code, a dedicated thread, quick demos and tests.
- **`await Task.Delay`**: anywhere inside `async` code. That includes retry back-off, polling loops and simulating I/O delays.

### Try it

Change `count` in Sample 3 from 50 to 200. How does the `Thread.Sleep` time change, and how does the `Task.Delay` time change? What does `ThreadPool.ThreadCount` show afterwards?
