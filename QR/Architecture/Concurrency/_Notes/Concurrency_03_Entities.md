## Concurrency
### Task, Thread, Process and Other Entities

#### [Back to Concurrency contents](../_Contents.md)

*Are Task, Thread and Process the same? Is there a general term for them, and what other similar entities exist?*

Short answer: **No. They work at different levels, and some aren't even the same *kind* of thing. A Task is a unit of work; Threads and Processes are units of execution that run work.**

### Two kinds of entities

| Kind | Meaning | Examples |
|---|---|---|
| **Unit of work** | *What* needs to be done | Task, Job, Work item, Future/Promise |
| **Unit of execution** | *Who/what runs it* and where | Process, Thread, Fiber, Coroutine, Goroutine |

In C#, for example, many `Task`s get run on a few thread-pool **threads** inside one **process**.

### The common terms

- **Process**: a running program with its **own memory space**, managed by the OS. Heavy and isolated.
- **Thread**: a sequence of execution **inside a process**, scheduled by the OS. Threads share the process's memory and each has its own stack.
- **Task**: an abstract unit of work, usually scheduled by a **runtime** onto threads (C# `Task`, Python `asyncio.Task`, Java `Runnable`/`Callable`).

### General umbrella terms

There's no single universal word, but these are the common ones:

- **Unit of execution / execution context**: covers processes, threads, fibers and coroutines.
- **Unit of work / work item**: covers tasks and jobs.
- **Flow (or thread) of control**: the most abstract term, meaning "a sequence of instructions being followed."
- **Schedulable entity**: anything a scheduler (OS or runtime) can pick to run.
  - Inside the Linux kernel, processes and threads are both just a `task_struct`, which the kernel calls a "task".
- **Worker**: an informal word for whatever does the running (a worker thread or worker process).

### Other similar entities

**Execution units, from heaviest to lightest:**

| Entity | Scheduled by | Notes |
|---|---|---|
| Virtual machine / container | Hypervisor / OS | Isolation units above the process level |
| **Process** | OS | Own memory |
| **Kernel thread** | OS | What "thread" normally means |
| **Fiber** | Your code (cooperative) | Like a thread, but it yields manually (Windows fibers, Ruby Fiber) |
| **Green thread / virtual thread** | Runtime | Java virtual threads, old Java green threads |
| **Goroutine** | Go runtime | Green thread with a growable stack |
| **Coroutine** | Runtime / language | A function that can suspend and resume (Python `async def`, Kotlin) |
| **Actor** | Actor runtime | State plus a mailbox, run on threads (Erlang process, Akka actor) |

**Work and result units:**

| Entity | Meaning |
|---|---|
| **Task** | Work to run, often with a result |
| **Job** | Often a larger or batch unit of work, sometimes spread across processes or machines |
| **Work item** | A queued piece of work (e.g. `ThreadPool.QueueUserWorkItem` in C#) |
| **Future / Promise** | A **handle to a result** that isn't ready yet; a C# `Task<T>` acts as one |
| **Callback / continuation** | "Run this when that finishes" |

### Mental model: "contains" vs "runs"

It's tempting to say "a process contains threads, and a thread contains tasks". Only the first half is true.

```
Process  ──contains──▶  Threads  ──run──▶  Tasks / work items
(owns memory)           (do the work)      (work to be done; scheduled onto any
                                            free thread, or on none while waiting)
```

**Process → threads: containment ✅**

- Every thread belongs to **exactly one** process and lives inside it.
- The threads share the process's memory.
- When the process ends, all its threads end too.

**Thread → tasks: "runs", not "contains" ❌**

A task is a **piece of work in a queue**, not something a thread owns:

- A task isn't tied to one thread. Queued tasks are picked up by whichever pool thread is free.
- **One task can move between threads.** An `async` task may start on thread 5, reach an `await`, and continue on thread 9.
- **A task can exist with no thread at all.** A `Task.Delay(1000)` task spends its whole second as just a timer, on no thread.

**Analogy: a kitchen**

| Concept | Kitchen |
|---|---|
| **Process** | The kitchen: it owns the space, the fridge and the tools (memory, resources) |
| **Thread** | A cook working in that kitchen |
| **Task** | An order ticket on the rail |
| **Thread pool** | The team of cooks taking tickets from the rail |
| **Runtime** | The kitchen manager: hires cooks, hands out tickets, sets oven timers, cleans up |
| **`await`** | The dish goes in the oven; the cook takes another ticket, and *any* free cook can finish the dish when the timer rings |

The kitchen **contains** cooks. Cooks **don't contain** tickets; they **pick them up and work on them**.

The same "runs, not contains" idea applies to the lighter entities: fibers, coroutines and goroutines are **scheduled onto** OS threads by a runtime (often many onto few), and a task's result is exposed through a **future/promise**.

### The naming trap

The same word means different things in different ecosystems:

- A Linux "task" is a thread or a process.
- A C# `Task` is a unit of work (and a future).
- An Erlang "process" is a tiny green thread, not an OS process.

When reading docs, always check: **who schedules it**, and **does it have its own memory**?

### What is a "runtime"?

The word has **two meanings**.

**Meaning 1: *when*.** Run time vs compile time: the period while the program is **running**, as opposed to when it's being compiled.

| | Compile time | Run time |
|---|---|---|
| When | `dotnet build` turns the code into a program | The program is executing |
| Example error | Passing a method with the wrong signature: a type mismatch | `NullReferenceException`, dividing by zero |

Hence phrases like "a *runtime error*" or "decided at *runtime*".

**Meaning 2: *what*.** The **runtime environment**: the software layer that **runs a program and provides services while it runs**. This is the meaning in "*scheduled by the runtime*".

```
Your code        (Main, your methods, your tasks)
   ▲ uses services from
Runtime          (.NET CLR: memory, threads, tasks, exceptions...)
   ▲ runs on top of
Operating system (processes, OS threads, files, network)
   ▲
Hardware         (CPU cores, RAM)
```

**What the .NET runtime (the CLR, *Common Language Runtime*) does:**

| Service | What it means |
|---|---|
| **JIT compilation** | Turns compiled IL code into machine code for the CPU, *while the program runs* |
| **Garbage collector (GC)** | Frees memory that's no longer used; no manual `free()` |
| **Thread pool** | Keeps and reuses worker threads |
| **Task scheduling** | Puts tasks onto pool threads and resumes code after an `await` |
| **Timers** | What `Task.Delay` uses instead of a sleeping thread |
| **Exceptions, type safety** | Catches errors like out-of-range array access and turns them into exceptions |

**Two schedulers, two layers:**

- **OS scheduler**: decides which **threads** run on which CPU cores, and when.
- **Runtime scheduler**: decides which **tasks** run on which threads.

**SDK vs runtime** (as listed by `dotnet --list-sdks` / `dotnet --list-runtimes`):

| | Contains | Needed to |
|---|---|---|
| **.NET Runtime** | Only what's needed to **run** apps (the CLR + libraries) | Run a finished app |
| **.NET SDK** | The runtime **plus** the compiler and `dotnet build/run/new` tools | Write and build apps |

**Runtimes in other languages:**

| Language | Runtime |
|---|---|
| C# | .NET CLR |
| Java, Kotlin | JVM (Java Virtual Machine) |
| Python | CPython interpreter (which has the GIL) |
| JavaScript | V8 engine + Node.js / browser event loop |
| Go | A small runtime built into every program (goroutine scheduler, GC) |
| Rust | Almost none built in; for async you add one, e.g. `tokio`, which calls itself an "async runtime" |
| C | Almost none, just a thin C library |

So "runtime" can mean a big environment (JVM, CLR) or a small library that only schedules tasks (`tokio`).

### Q: When you `await` a C# `Task` that's waiting on a network call, how many threads are tied up during that wait?

**Answer: zero.** *"There is no thread."* (Stephen Cleary)

```csharp
public async Task<string> GetPageAsync(HttpClient client)
{
    // 1. Runs on the calling thread up to here
    var html = await client.GetStringAsync("https://example.com");
    // 3. Resumes here later, possibly on a different thread
    return html;
}
```

What happens step by step:

1. The calling thread runs the method until it reaches the `await`. The network request is handed off to the OS (I/O completion ports on Windows, epoll/kqueue on Linux/macOS).
2. The `Task` isn't finished, so the method **returns early** and the calling thread is freed. A thread-pool thread goes back to the pool; a UI thread goes back to handling clicks and redraws. **While the data travels over the network, no thread is waiting on it.** The network card and the OS do the work, and the pending `Task` is just an object in memory.
3. When the response arrives, the OS signals completion and the rest of the method (the **continuation**) is scheduled:
   - on a **thread-pool thread** by default (e.g. ASP.NET Core, console apps), or
   - back on the **original thread** if a `SynchronizationContext` was captured (e.g. WinForms/WPF UI thread), unless you used `ConfigureAwait(false)`.

This is why async scales: 10,000 pending HTTP calls can wait on a handful of threads.

**When a thread *is* tied up:**

| Code | Threads blocked during the wait |
|---|---|
| `await client.GetStringAsync(...)` | 0 |
| `client.GetStringAsync(...).Result` / `.Wait()` | 1 (the caller blocks; can also deadlock on a UI thread) |
| `await Task.Run(() => client.GetString(...))` (sync I/O inside) | 1 (a pool thread blocks instead) |
| `await Task.Run(() => HeavyCalculation())` | 1 (but that's CPU work, so a thread *should* be busy) |

**Takeaway:** for **I/O-bound** work, `async/await` is concurrency **without** multi-threading during the wait. For **CPU-bound** work, `Task.Run` puts the work on a thread-pool thread, which gives you real multi-threading.

### In practice: 50 tasks, blocking vs non-blocking waits

This experiment shows tasks, pool threads and timers working together.

```csharp
using System.Diagnostics;

const int count = 50;

// Part 1 ❌ Thread.Sleep: each wait holds a thread-pool thread for the whole second
var sw = Stopwatch.StartNew();
await Task.WhenAll(Enumerable.Range(0, count).Select(_ =>
    Task.Run(() => Thread.Sleep(1000))));
Console.WriteLine($"Thread.Sleep: {sw.ElapsedMilliseconds} ms, pool threads: {ThreadPool.ThreadCount}");

// Part 2 ✅ Task.Delay: no thread is held during the wait
sw.Restart();
await Task.WhenAll(Enumerable.Range(0, count).Select(_ =>
    Task.Delay(1000)));
Console.WriteLine($"Task.Delay:   {sw.ElapsedMilliseconds} ms");
```

Example result on a **12-core** machine: Part 1 takes **4+ seconds** with **10+ pool threads**; Part 2 takes about **1 second**.

**Part 1: why 4+ seconds?**

1. `Task.Run` creates **50 tasks, not 50 threads**. It only **queues** 50 work items; the pool decides how many threads run them.
2. The pool starts with about **one thread per CPU core** (its minimum, here ~12).
3. ~12 tasks start and each **blocks** its thread for 1 second. The rest wait in the queue.
4. When a sleep finishes, that thread is **reused** for the next queued task.
5. The pool notices queued work and adds threads, but **slowly** (roughly one every half second).

So 50 ÷ ~12 ≈ 4+ "rounds" of 1 second each. This is **thread-pool starvation**.

**Part 2: why about 1 second?**

- `Task.Delay(1000)` sets up a **timer**: 50 delays means 50 timers and **zero** waiting threads.
- All 50 timers count down **at the same time**. When they fire, completing each task borrows a pool thread for only a few **microseconds**.
- So no thread is used *during the wait*. Nobody waits; the timers count down.

| | What waits during the second | Limited by | Total |
|---|---|---|---|
| `Thread.Sleep` | A **thread** per task | The number of pool threads (~cores) | ~count ÷ cores seconds |
| `Task.Delay` | A **timer** per task | Nothing | ~1 s |

**Try it:**

1. Add `ThreadPool.SetMinThreads(50, 50);` at the top. What happens to Part 1's time, and why?
2. Swap the order so Part 2 runs first, and print `ThreadPool.ThreadCount` after it. Does Part 2 alone grow the pool?

### Think about it

- In the experiment above, who started the extra pool threads when work got stuck in Part 1: the OS or the runtime? And who decided which CPU core each of those threads ran on?
- While Part 2's 50 delays are counting down, roughly how many threads are busy with them, and how many tasks exist?
