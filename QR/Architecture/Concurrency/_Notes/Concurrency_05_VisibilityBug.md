## Concurrency
### Visibility Bugs

#### [Back to Concurrency contents](../_Contents.md)

*What is a "visibility bug", and why is it named so?*

Short answer: **a visibility bug is when one thread writes a value, but another thread doesn't see that write (late, or never). The write did happen; it just isn't *visible* to the other thread.**

### Why "visibility"?

The word comes from **memory models**: the rules a language or platform defines for **when a write by one thread is guaranteed to be visible to another**.

- Java memory model
- .NET (C#) memory model
- C++ memory model

Without the right guarantees (`volatile`, locks, atomics...), a memory model does **not** promise that another thread will ever see your write.

### Example

```csharp
bool stop = false;   // shared, not volatile

// Thread A
while (!stop) { }    // waits for the flag

// Thread B
stop = true;         // sets the flag
```

Thread B really set `stop = true`, but thread A may loop forever because it still sees `false`.

### Why a write can stay invisible

**1. The compiler/JIT keeps the value in a register (the most common cause)**

The JIT sees that nothing inside the loop changes `stop`. From a single-threaded point of view that's correct, so it may read `stop` **once** and reuse the value:

```
Your code:            What the JIT may produce:
while (!stop) { }  →  if (!stop) { while (true) { } }
```

Thread A never reads memory again, so B's write is never seen. This is called **hoisting** (moving the read out of the loop).

This is why visibility bugs often appear only in **Release** builds, where optimisations are on, and not in Debug.

**2. The CPU reorders memory operations**

- CPUs use **store buffers**: a write can sit in one core's buffer briefly before other cores can see it.
- CPUs may **reorder** reads and writes. ARM (including Apple Silicon) reorders more than x86.
- So writes can become visible **late**, or in a **different order** from the one they were written in.

**A common myth:** "each core has its own cache, so it sees an old value." Modern CPU caches are **coherent**: the hardware keeps them in sync. The real causes are **registers, compiler optimisations, store buffers and reordering**.

### Visibility is one of three properties

Most thread-safety problems come down to one of these:

| Property | Question | Bug when broken | Example |
|---|---|---|---|
| **Atomicity** | Can another thread get in partway through? | Race condition / lost update | `count++` |
| **Visibility** | Will other threads **see** my write? | Visibility bug (stale value) | `while (!stop)` |
| **Ordering** | Will other threads see my writes **in the order I wrote them**? | Reordering bug | Seeing `ready == true` before `data` is set |

A visibility bug is **not** the same as a race on `count++`:

- `count++` is a **read-modify-write** that can be interrupted partway through (an atomicity problem).
- `stop = true` is a **single write**. Nothing is interrupted; the other thread just doesn't **see** it.

### Ordering example

```csharp
int data = 0;
bool ready = false;

// Thread A (producer)
data = 42;
ready = true;

// Thread B (consumer)
if (ready)
    Console.WriteLine(data);   // may print 0!
```

The compiler or CPU may let thread B see `ready = true` **before** it sees `data = 42`. Making `ready` volatile fixes this: a volatile write publishes everything written before it, and a volatile read sees everything published before it.

### How to fix it

Anything that creates a **memory barrier** (also called a **fence**) forces writes to be published and reads to be fresh:

| Fix | How |
|---|---|
| `volatile bool stop;` | Every read and write goes to memory; the JIT can't cache it in a register |
| `Volatile.Read(ref stop)` / `Volatile.Write(ref stop, true)` | The same guarantee, applied only to the reads and writes you choose |
| `lock` | Entering and leaving a lock includes barriers, so it gives **visibility** as well as mutual exclusion |
| `Interlocked.*` | A full barrier, plus atomicity |
| `CancellationToken` | The usual .NET way to signal "stop"; the barriers are handled for you |

```csharp
private volatile bool _stop;

// Thread A
while (!_stop) { }   // now re-reads _stop every time

// Thread B
_stop = true;        // A will see this
```

**`volatile` fixes visibility, not atomicity.** `volatileCount++` is still read, add, write, so it can still lose updates. Use `Interlocked.Increment` or `lock` for that.

### How to reproduce one

- Use a non-volatile flag with an **empty** spin loop.
- Run it in **Release** mode (`dotnet run -c Release`). In Debug it usually works.
- Let the loop run for a while (e.g. 1 second) so the JIT has time to optimise it.
- If it still doesn't hang, turn off tiered compilation: `DOTNET_TieredCompilation=0 dotnet run -c Release`.
- Results depend on the .NET version and the CPU, so it may not reproduce everywhere.

Runnable program: [Replication (C#)](../_Samples/Replication_C%23.md), sample 4.

### Summary

- A **visibility bug** = a write that happened but another thread doesn't see.
- Named after the **memory model** term "visibility".
- Caused by **registers/JIT hoisting**, **store buffers** and **reordering**, not by "stale caches".
- Often appears only in **Release** builds.
- Fixed by **memory barriers**: `volatile`, `Volatile.Read/Write`, `lock`, `Interlocked`, `CancellationToken`.
- `volatile` gives visibility and ordering, **not** atomicity.

### Think about it

- Why doesn't adding `Console.WriteLine` inside the loop let the bug reproduce? (Hint: can the JIT still assume `stop` never changes across a method call?)
- If x86 CPUs reorder less than ARM, could code that works on an Intel machine break on an Apple Silicon Mac? Why?
- `lock` is usually explained as "only one thread at a time". Why does it **also** fix visibility?
