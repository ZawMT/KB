## Concurrency
### Synchronisation: Overview

#### [Back to Concurrency contents](../_Contents.md)

*What is synchronisation in concurrent code, what are the approaches, and how do languages support it?*

Short answer: **Synchronisation is how concurrent tasks coordinate access to shared data and resources. The approaches range from avoiding shared state, through atomic operations, to locks, semaphores and signals (the *synchronisation primitives*). Every mainstream language offers similar primitives under different names, and some (Go, Rust) push you towards message passing or compile-time safety instead.**

### Why it's needed

Whenever tasks **share mutable state**, their steps can interleave in harmful ways.

```
counter++   is really   read → add 1 → write

Thread A: read (0)                 add → write (1)
Thread B:        read (0)  add → write (1)          ← one increment is lost
```

Synchronisation isn't an *alternative* to threads, async or processes ([Approaches](Concurrency_02_Approaches.md)). It's what you need **when those tasks share memory or a resource**. Message-passing approaches (actors, channels) are designed to need much less of it.

### Key terms

| Term | Meaning |
|---|---|
| **Critical section** | Code that touches shared state and must not run in more than one task at a time |
| **Race condition** | The result depends on the timing/order of tasks |
| **Data race** | Two threads access the same memory at the same time, at least one writes, with no synchronisation (a specific kind of race condition) |
| **Atomic** | Happens as one indivisible step; no one can see it half-done |
| **Visibility** | Whether a write by one thread is *seen* by another (CPUs and compilers cache and reorder; the **memory model** defines the rules) |
| **Reentrant lock** | The same thread can acquire it again without deadlocking itself |
| **Blocking vs non-blocking** | Waiting puts the thread to sleep, vs retrying/continuing without sleeping |
| **Lock-free** | Some thread always makes progress, without locks (usually built on compare-and-swap, CAS) |

### What synchronisation solves

| Problem | Example | Typical primitive |
|---|---|---|
| **Mutual exclusion**: one at a time | Two threads updating a counter | Lock / mutex |
| **Limiting**: at most N at a time | At most 3 concurrent DB connections | Semaphore |
| **Signaling**: wait until something happens | "Start when the data is loaded" | Event, condition variable, future |
| **Coordination**: wait for a group | "All workers finish phase 1 before phase 2" | Latch, barrier, wait group |
| **Many readers, one writer** | A cache read often, updated rarely | Reader-writer lock |
| **Run exactly once** | Lazy initialisation of a singleton | Once / lazy |

### The approaches, from safest to most manual

#### 1. Avoid sharing mutable state

The best synchronisation is none.

- **Immutable data**: can't change, so it's safe to read from any thread.
- **Confinement / thread-local data**: each task has its own copy.
- **Message passing**: tasks send data to each other instead of sharing it (channels, actors, queues). The owner changes; the data isn't shared.
  > *"Don't communicate by sharing memory; share memory by communicating."* (Go proverb)

#### 2. Use thread-safe building blocks

- **Concurrent collections** (concurrent maps, queues) do the locking internally.
- **Higher-level constructs** like futures/promises, parallel loops and lazy initialisation hide the synchronisation for you.

#### 3. Atomic operations

Single-variable updates done atomically by the CPU, with no lock:

- atomic increment/add/exchange
- **compare-and-swap (CAS)**: "set it to X only if it's still Y", which is the foundation of lock-free code

Fast, but they only cover **one variable**. Updating two related values together still needs a lock.

#### 4. Locks (mutual exclusion)

Only the holder of the lock can run the critical section; everyone else waits.

- **Mutex / lock / monitor**: the everyday tool.
- **Reader-writer lock**: many readers at once, **or** one writer.
- **Spin lock**: busy-waits instead of sleeping; only for extremely short sections.
- **Condition variable**: lets a lock holder *wait* for a condition and be woken up by another thread ("queue not empty").

#### 5. Semaphores (limiting)

A counter of permits: up to **N** tasks can hold one at a time. A semaphore with N = 1 behaves like a lock (but usually has **no owner**, so any task can release it).

#### 6. Signaling and coordination

- **Event / gate**: wait until another task says "go".
- **Latch / countdown / wait group**: wait until N things have finished.
- **Barrier**: all participants wait for each other, then all continue (repeated per phase).
- **Future / promise**: wait for a single result to become available.

#### Pessimistic vs optimistic

| Style | Idea | Examples |
|---|---|---|
| **Pessimistic** | Assume conflicts; lock first, then work | Locks, mutexes, `SELECT ... FOR UPDATE` |
| **Optimistic** | Assume no conflict; work, then check and retry if someone else changed it | CAS loops, row version numbers / ETags |

Optimistic works well when conflicts are rare; pessimistic when they're common.

### How languages support it

#### Primitive names across languages

| Concept | C# | Java | Python | Go | Rust | C++ |
|---|---|---|---|---|---|---|
| Lock / mutex | `lock`, `Lock` (.NET 9+), `Monitor` | `synchronized`, `ReentrantLock` | `threading.Lock`, `RLock` | `sync.Mutex` | `Mutex<T>` | `std::mutex` |
| Reader-writer lock | `ReaderWriterLockSlim` | `ReentrantReadWriteLock`, `StampedLock` | (none in std lib) | `sync.RWMutex` | `RwLock<T>` | `std::shared_mutex` |
| Semaphore | `SemaphoreSlim` | `Semaphore` | `threading.Semaphore` | buffered channel, `x/sync/semaphore` | `tokio::sync::Semaphore` (none in std) | `std::counting_semaphore` (C++20) |
| Condition variable | `Monitor.Wait/Pulse` | `wait/notify`, `Condition` | `threading.Condition` | `sync.Cond` | `Condvar` | `std::condition_variable` |
| Event / one-shot signal | `ManualResetEventSlim`, `TaskCompletionSource` | `CompletableFuture` | `threading.Event` | `close(ch)` | `tokio::sync::oneshot`, `Notify` | `std::promise` / `std::future` |
| Wait for N | `CountdownEvent` | `CountDownLatch` | (use `Barrier` or join) | `sync.WaitGroup` | (join handles) | `std::latch` (C++20) |
| Barrier | `Barrier` | `CyclicBarrier`, `Phaser` | `threading.Barrier` | (none in std lib) | `std::sync::Barrier` | `std::barrier` (C++20) |
| Atomics | `Interlocked` | `AtomicInteger`, `AtomicReference`... | (none in std lib) | `sync/atomic` | `std::sync::atomic` | `std::atomic` |
| Run once | `Lazy<T>` | holder idiom, `static` init | (module import runs once) | `sync.Once` | `OnceLock` | `std::call_once` |
| Concurrent collections | `ConcurrentDictionary`, `ConcurrentQueue` | `ConcurrentHashMap`, `BlockingQueue` | `queue.Queue` | `sync.Map` | (crates, e.g. `dashmap`) | (none in std lib) |
| Channels | `System.Threading.Channels` | `BlockingQueue` | `queue.Queue`, `asyncio.Queue` | `chan` (built-in) | `std::sync::mpsc` | (none in std lib) |

#### What's distinctive about each

**C#**
- `lock` is a language keyword, built on `Monitor` (or the `Lock` type in .NET 9+).
- You **can't `await` inside a `lock`**. Use `SemaphoreSlim(1, 1)` with `await WaitAsync()` for async code.
- Rich library: `System.Threading`, `System.Collections.Concurrent`, `System.Threading.Channels`.

**Java**
- `synchronized` is a keyword (methods or blocks); every object has a built-in monitor with `wait/notify`.
- `volatile` guarantees visibility. The **Java Memory Model** was one of the first formal memory models.
- `java.util.concurrent` has the widest standard set of primitives (latches, barriers, phasers, atomics, concurrent maps).

**Python**
- `threading` has the classic primitives; `asyncio` has its **own async versions** (`asyncio.Lock`, `Semaphore`, `Event`, `Queue`), and `multiprocessing` has process-safe ones.
- ⚠️ The GIL does **not** make your code thread-safe. `counter += 1` can still lose updates, because the GIL can switch threads between the read and the write.

**Go**
- Built for message passing: **goroutines + channels + `select`** are part of the language.
- The `sync` package still has `Mutex`, `RWMutex`, `WaitGroup` and `Once` for when shared state is simpler.
- Built-in **race detector**: `go run -race` / `go test -race`.

**Rust**
- **Data races are compile-time errors.** The ownership rules and the `Send`/`Sync` traits stop you from sharing data across threads unsafely.
- `Mutex<T>` **wraps the data it protects**. You can't touch the data without holding the lock. Sharing between threads uses `Arc<Mutex<T>>`.
- Async runtimes (e.g. `tokio`) provide async-aware `Mutex`, `RwLock`, `Semaphore` and channels.

**JavaScript / TypeScript**
- One thread per event loop, so ordinary code needs **no locks**: nothing else runs between two statements.
- ⚠️ But **async interleaving** is still possible: state can change across an `await`. That's a race condition without threads.
- True shared memory only exists with Web Workers / Node `worker_threads` + `SharedArrayBuffer`, synchronised with `Atomics` (`Atomics.add`, `Atomics.wait/notify`). Browsers also have the Web Locks API (`navigator.locks`).

**C / C++**
- C: POSIX threads (`pthread_mutex_t`, `pthread_cond_t`, `sem_t`) and C11 `<stdatomic.h>`.
- C++: `std::mutex` with RAII helpers (`std::lock_guard`, `std::scoped_lock`, `std::unique_lock`), `std::atomic`, and C++20 `std::latch`, `std::barrier`, `std::counting_semaphore`.
- Most manual of all: a data race is **undefined behaviour**.

#### Overall trend

| Language style | Approach to safety |
|---|---|
| C, C++ | Primitives provided; correctness is up to you |
| C#, Java, Python | Rich libraries of primitives + concurrent collections |
| Go, Erlang/Elixir | Message passing built into the language |
| Rust | The compiler enforces safe sharing |
| JavaScript | Avoids shared memory by default (single-threaded event loop) |

### Beyond a single process

The in-memory primitives above only work **inside one process**. Wider scopes need other tools:

| Scope | Tools |
|---|---|
| Across processes on one machine | Named mutex/semaphore (OS-level), file locks |
| Shared database | Transactions, row locks (pessimistic), version columns (optimistic) |
| Across machines | Distributed locks (Redis, ZooKeeper, etcd), leases, consensus protocols |

### Hazards that synchronisation introduces

| Hazard | Meaning |
|---|---|
| **Deadlock** | A holds lock 1 and waits for lock 2; B holds lock 2 and waits for lock 1. Avoid by always taking locks in the same order. |
| **Livelock** | Threads keep reacting to each other but make no progress |
| **Starvation** | A thread never gets its turn |
| **Contention** | Many threads fight over one lock, so the code effectively runs serially |
| **Priority inversion** | A high-priority thread waits on a lock held by a low-priority one |

### Rule of thumb

1. **Don't share** mutable data (immutability, confinement, message passing).
2. If you must share, use a **concurrent collection** or an **atomic**.
3. If you need a critical section, use a **plain lock**, kept as short as possible (or an async-aware lock/semaphore in async code).
4. Reach for specialised primitives only when you have a specific reason.

### Think about it

Why can't you `await` inside a C# `lock` block, while `SemaphoreSlim` is fine with it? *(Hint: which thread "owns" a lock, and which thread runs the code after an `await`?)*
