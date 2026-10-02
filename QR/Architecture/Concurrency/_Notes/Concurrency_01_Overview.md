## Concurrency
### Concurrency vs Multi-threading

#### [Back to Concurrency contents](../_Contents.md)

*Can multi-threading and concurrency be treated as the same topic?*

Short answer: **No. Multi-threading is one way to achieve concurrency. They're related, but not the same thing.**

### Concurrency: the concept

**Concurrency** means a program is dealing with **multiple tasks whose lifetimes overlap**. The tasks make progress in interleaved steps, which may or may not happen at the exact same instant.

It's about **structure**: many things are in flight at once.

### Multi-threading: one implementation

**Multi-threading** means using **multiple OS/runtime threads** within a single process. It's one tool for achieving concurrency, and it can also give you parallelism.

### You can have one without the other

| Scenario | Concurrent? | Multi-threaded? |
|---|---|---|
| `async/await` on a single thread (JS event loop, Python `asyncio`) | ✅ | ❌ |
| Multiple processes (Python `multiprocessing`) | ✅ | ❌ (per process) |
| Threads on a single-core CPU (time-sliced) | ✅ | ✅, but not parallel |
| Threads on a multi-core CPU | ✅ | ✅ and parallel |

### The third term: parallelism

- **Concurrency**: *dealing with* many things at once (structure)
- **Parallelism**: *doing* many things at the same instant (execution, which needs multiple cores)

> *"Concurrency is not parallelism."* (Rob Pike)

### Why the distinction matters

- **I/O-bound work** (network calls, file reads, API calls) usually only needs concurrency, e.g. `async`. Threads add overhead you don't need.
- **CPU-bound work** needs parallelism: threads or processes on multiple cores.
  - Python's GIL is the classic trap: threads give concurrency but **not** CPU parallelism. (Python 3.13+ also has an optional free-threaded build without the GIL.)
- **Shared-state problems** like race conditions, deadlocks and locks belong to **concurrency in general**, not just threads. Async code can have race conditions too, at its `await` points.

### Bottom line

**Multi-threading ⊂ concurrency.** Treat concurrency as the umbrella topic, with threads, async and processes as different ways to implement it.

### Think about it

In C#, is `async/await` with `Task` concurrency, multi-threading, or both? *(Hint: it depends. When does a `Task` actually run on another thread?)*
