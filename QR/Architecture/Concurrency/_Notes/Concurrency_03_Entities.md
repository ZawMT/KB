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

### Mental model

```
Machine
 └─ Process (own memory)
     └─ Thread (OS-scheduled)
         └─ Fiber / coroutine / goroutine (runtime-scheduled)
             └─ runs → Task / work item (what to do)
                         └─ produces → Future / Promise (the result handle)
```

### The naming trap

The same word means different things in different ecosystems:

- A Linux "task" is a thread or a process.
- A C# `Task` is a unit of work (and a future).
- An Erlang "process" is a tiny green thread, not an OS process.

When reading docs, always check: **who schedules it**, and **does it have its own memory**?

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
