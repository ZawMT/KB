## Concurrency
### Replicating Concurrency Bugs

#### [Back to Concurrency contents](../_Contents.md)

*What is the best way to replicate a concurrency issue such as a race condition or deadlock?*

Short answer: **make the bad interleaving very likely, run it many times, and check an invariant that a race would break.** Concurrency bugs depend on timing, so a single passing run proves nothing.

### Why concurrency bugs are hard to reproduce

- The bug only happens when threads interleave in one **particular order**.
- The "danger window" is often just a few **nanoseconds** (e.g. between reading and writing a variable).
- The OS scheduler decides when threads run, so every run is different.
- Adding logging or a debugger changes the timing and can make the bug disappear (a "**heisenbug**").

The techniques below increase the chance of hitting the bad ordering, or force it.

### 1. Widen the race window

Most races happen in a **read-modify-write** gap. `count++` looks like one step, but it's three: **read**, **add**, **write**. If two threads read the same value before either writes, one update is lost.

On a fast CPU the gap is tiny, so the bug rarely shows. Stretch it on purpose:

```csharp
var temp = _count;      // read
Thread.Yield();         // or Thread.Sleep(0) / Task.Delay(1): hands the CPU to another thread
_count = temp + 1;      // write, which may overwrite another thread's update
```

This is the **single biggest lever**. A bug that shows once in a million runs can start showing on nearly every run.

### 2. Maximise contention with a starting gate

Threads created in a loop start **staggered**, so the first may finish before the last has even started. Then they never actually overlap.

Make all threads wait at a **gate** and release them together:

```csharp
var gate = new ManualResetEventSlim(false);
// each worker: gate.Wait(); then hammer the shared state
// main: start all workers, then gate.Set();
```

Other gate options: `Barrier`, `CountdownEvent`.

Also:

- Use **more threads than CPU cores**.
- Use **many iterations** per thread.

### 3. Assert an invariant and repeat

Pick something that **must be true** if the code is correct:

| Scenario | Invariant |
|---|---|
| Counter | Final count == threads × iterations |
| Bank account | Balance never goes negative |
| Cache / dictionary | Each key is created exactly once |
| Queue | Every item enqueued is dequeued exactly once |

Run the whole scenario in a loop (e.g. 1,000 times) and **count the failures**. A failure rate ("37 out of 1,000") tells you far more than one pass or fail, and lets you confirm that a fix brings it to 0.

### 4. Test in Release mode, not only Debug

Some bugs only appear with **JIT optimisations**, which are turned off in Debug builds.

The classic example is a **visibility bug**:

```csharp
private bool _stop;               // not volatile

// worker thread
while (!_stop) { }                // the JIT may read _stop once and loop forever

// another thread
_stop = true;
```

In Debug this usually works. In Release the JIT can **hoist** the read out of the loop, so the worker never sees the change. Fixes: `volatile`, `Volatile.Read`, or a proper signal such as `CancellationToken`. More detail: [Visibility bug](Concurrency_05_VisibilityBug.md).

### 5. Force the exact interleaving (deterministic)

Techniques 1–4 make the bug **likely**. For a unit test that must fail **every time**, script the bad ordering instead:

1. Add a **test hook** to the code under test (e.g. a callback or a `SemaphoreSlim` that is a no-op in production).
2. The test **pauses thread A** right after its read.
3. The test lets **thread B** run to completion.
4. The test **resumes thread A**, which now writes a stale value.

The result is fully repeatable, with no `Sleep` and no luck involved. The trade-off is that the production code needs a seam for the test to hook into.

### 6. Tooling

| Tool | What it does |
|---|---|
| **Microsoft Coyote** | Systematically explores thread interleavings in .NET and can **replay** a failing schedule |
| **Concurrency Visualizer** (Visual Studio) | Shows what each thread was doing over time |
| **PerfView** | Traces thread, lock and thread-pool events |
| **Thread sanitisers** (other languages) | e.g. `-race` in Go, ThreadSanitizer for C/C++/Rust, which detect data races at run time |

### Applying the techniques to common bug types

**Lost update** (e.g. `count++`):
Widen the read-modify-write gap (technique 1) and use a gate (technique 2).

**Check-then-act** (e.g. `if (!dict.ContainsKey(k)) dict.Add(k, v);`):
Put a yield **between the check and the act**. Two threads both pass the check, and the second `Add` throws or overwrites.

**Deadlock** (lock-ordering):

| Thread A | Thread B |
|---|---|
| lock X | lock Y |
| *wait at barrier* | *wait at barrier* |
| lock Y (blocked: B holds Y) | lock X (blocked: A holds X) |

Put a `Barrier` between the first and second lock. Both threads are then guaranteed to hold their first lock before reaching for the second, so the program **hangs every time** instead of occasionally.

**Visibility** (non-volatile flag):
Run in **Release** mode (technique 4).

### Summary

| Technique | Makes the bug... |
|---|---|
| Widen the race window | Much more **likely** |
| Starting gate + many threads | More **likely** (real overlap) |
| Invariant + repeat | **Measurable** (failure rate) |
| Release build | **Possible** (optimisation-only bugs) |
| Test hooks / scripted interleaving | **Certain** (deterministic) |
| Coyote / sanitisers | **Found and replayed** systematically |

### Try it

Runnable versions of each technique: [Replication (C#)](../_Samples/Replication_C%23.md)

1. Write a counter incremented by 10 threads × 100,000 iterations each. Check the final count.
2. Run it again with a **starting gate**. Does the failure rate change?
3. Add `Thread.Yield()` between the read and the write. How often does it fail now?
4. Fix it twice: once with `lock`, once with `Interlocked.Increment`. Run each 1,000 times and confirm the invariant always holds.
5. Write the two-lock deadlock with a `Barrier`, then fix it by making both threads take the locks in the **same order**.

### Think about it

- Why does adding a `Console.WriteLine` inside the loop sometimes make a race condition **disappear**?
- If a stress test passes 10,000 times in a row, does that prove the code is thread-safe? What would give you more confidence?
- Technique 5 adds test hooks to production code. What are the costs, and how could you keep them out of the release build?
