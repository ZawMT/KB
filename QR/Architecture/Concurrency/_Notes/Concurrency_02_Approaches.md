## Concurrency
### Approaches to Concurrency

#### [Back to Concurrency contents](../_Contents.md)

*Apart from multi-threading, what are the other approaches to concurrency?*

Short answer: **Multi-processing, async/event loops, green/virtual threads, actors, CSP channels, data parallelism, reactive streams and distributed systems. They differ in whether tasks share memory or pass messages, and in who schedules the tasks.**

### 1. Multi-processing

Run several **separate processes**, each with its own memory.

- **Pros:** real parallelism and strong isolation (one crash doesn't take the others down). In Python it also gets around the GIL.
- **Cons:** heavy to start; sharing data needs serialisation or IPC (pipes, sockets, shared memory).
- **Examples:** Python `multiprocessing`, pre-forked web server workers (Gunicorn), Chrome's process-per-tab design.

### 2. Async I/O / event loop

A **single thread** runs many tasks. Each task gives up control while waiting on I/O, and an event loop resumes it when the result is ready.

- **Pros:** very cheap per task; ideal for I/O-bound work.
- **Cons:** no CPU parallelism; one blocking call stalls everything on the loop.
- **Examples:** Node.js, Python `asyncio`, Rust `tokio`. C# `async/await` belongs here too, although its continuations may resume on thread-pool threads.

### 3. Coroutines / green threads / virtual threads

**Lightweight threads managed by the runtime**, not the OS. The runtime multiplexes many of them onto a few OS threads ("M:N scheduling").

- **Pros:** write ordinary blocking-style code, but run millions of tasks.
- **Examples:** Go goroutines, Java 21+ virtual threads, Kotlin coroutines, Erlang processes.

### 4. Actor model

Each **actor** owns its own state and talks to others **only by sending messages**. Nothing is shared, so there are no locks.

- **Pros:** whole classes of race conditions disappear; scales naturally across machines.
- **Examples:** Erlang/Elixir, Akka (JVM), Microsoft Orleans (.NET).

### 5. CSP (Communicating Sequential Processes) / channels

Independent tasks communicate through **channels**. Similar to actors, but the channel is the thing you name and pass around, not the receiver.

> *"Don't communicate by sharing memory; share memory by communicating."* (Go proverb)

- **Examples:** Go channels, Rust `mpsc`, Kotlin channels, C# `System.Threading.Channels`.

### 6. Data parallelism

Apply the **same operation to many pieces of data at once**.

- **Examples:** SIMD instructions, GPU computing (CUDA), C# `Parallel.For` / PLINQ, NumPy vectorisation.

### 7. Reactive / stream-based

Model work as **streams of events** and combine them with operators. The scheduler decides where each piece runs.

- **Examples:** Rx (RxJS, Rx.NET), Project Reactor, Akka Streams.

### 8. Distributed concurrency

Spread the work **across machines** using message queues or job systems.

- **Examples:** RabbitMQ, Kafka consumers, Celery, Kubernetes jobs.

### Comparison

| Approach | Shared memory? | Parallel on multi-core? | Best for |
|---|---|---|---|
| Threads | Yes (needs locks) | ✅ | General purpose, CPU work |
| Processes | No | ✅ | CPU work, isolation |
| Async / event loop | Yes (one thread) | ❌ | I/O-bound |
| Green / virtual threads | Yes | ✅ (M:N) | Massive I/O concurrency |
| Actors | No (messages) | ✅ | Fault-tolerant, distributed systems |
| CSP / channels | No (messages) | ✅ | Pipelines, coordination |
| Data parallelism | Yes | ✅ | Number crunching |

### Bottom line

Two questions separate these approaches:

1. Do tasks **share memory** or **pass messages**?
2. Who **schedules** the tasks: the OS, the runtime, or your own code?

Most concurrency bugs come from shared memory, which is why actors and channels exist.

### Think about it

Python `asyncio` and Go goroutines can both handle 100k network connections. Why can goroutines also use all your CPU cores while `asyncio` can't?
