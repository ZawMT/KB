## Concurrency
### Replicating Concurrency Bugs in C#

#### [Back to Concurrency contents](../_Contents.md)

*How can each technique for replicating a concurrency bug be tried out in C#?*

Short answer: **each sample below is a small program that reproduces one bug on purpose and reports how often it happened.** The samples only show the bug; fixing it is left as the "Try it" step at the end of each one.

> Each sample is a complete console program (top-level statements, .NET 6+). Paste one into `Program.cs` and run it with `dotnet run` (or `dotnet run -c Release` where stated).

Results vary between machines and runs, which is exactly the point: concurrency bugs depend on timing.

### 1. Widen the race window

The same lost-update bug is run two ways: plain `count++` (a tiny gap between read and write), and an explicit read, `Thread.Yield()`, write (a wide gap).

```csharp
using System.Threading;

const int ThreadCount = 4;
const int Iterations = 10_000;
const int Runs = 20;

Console.WriteLine($"Expected per run: {ThreadCount * Iterations}");
Measure("Narrow window (count++)    ", widen: false);
Measure("Wide window (Thread.Yield) ", widen: true);

void Measure(string label, bool widen)
{
    int failures = 0, worst = int.MaxValue;
    for (int run = 0; run < Runs; run++)
    {
        int result = RunOnce(widen);
        if (result != ThreadCount * Iterations) failures++;
        worst = Math.Min(worst, result);
    }
    Console.WriteLine($"{label} failures: {failures}/{Runs}, worst result: {worst}");
}

int RunOnce(bool widen)
{
    var counter = new Counter();
    var threads = new Thread[ThreadCount];

    for (int t = 0; t < ThreadCount; t++)
    {
        threads[t] = new Thread(() =>
        {
            for (int i = 0; i < Iterations; i++)
            {
                if (widen)
                {
                    var temp = counter.Value;   // read
                    Thread.Yield();             // give another thread the chance to run
                    counter.Value = temp + 1;   // write (may overwrite another thread's update)
                }
                else
                {
                    counter.Value++;            // read, add, write in one line, but still 3 steps
                }
            }
        });
    }

    foreach (var t in threads) t.Start();
    foreach (var t in threads) t.Join();
    return counter.Value;
}

class Counter
{
    public int Value;
}
```

**What to expect:** the narrow version often shows **0 failures**. The code is still broken: 10,000 increments finish in microseconds, which is quicker than starting the next thread, so the threads barely overlap. The wide version fails on almost every run and loses a large share of the updates.

Example result (4 threads): narrow `0/20`, wide `20/20` with a worst result of about `10,000`. That is roughly **¼ of 40,000**, so in that run the four threads were effectively in step: they read the same value, yielded, and wrote the same value back, and three threads' work was lost.

The narrow `0/20` is a good example of why "it passed" proves nothing. Increase `Iterations` to `1_000_000`, or add a starting gate (sample 2), and the narrow version starts failing too.

**Try it:** fix `RunOnce` with `lock`, then with `Interlocked.Increment`, and confirm both versions report `0` failures.

### 2. Starting gate

Each thread does only a little work, so without a gate the first threads often finish before the last ones have started. With a gate, all threads are released at the same moment.

```csharp
using System.Threading;

int ThreadCount = Environment.ProcessorCount * 2;   // more threads than cores
const int Iterations = 2_000;
const int Runs = 200;

Console.WriteLine($"{ThreadCount} threads, expected per run: {ThreadCount * Iterations}");
Measure("Without gate", useGate: false);
Measure("With gate   ", useGate: true);

void Measure(string label, bool useGate)
{
    int failures = 0;
    for (int run = 0; run < Runs; run++)
    {
        if (RunOnce(useGate) != ThreadCount * Iterations) failures++;
    }
    Console.WriteLine($"{label} failures: {failures}/{Runs}");
}

int RunOnce(bool useGate)
{
    var counter = new Counter();
    using var ready = new CountdownEvent(ThreadCount);   // "all threads are at the gate"
    using var gate = new ManualResetEventSlim(false);    // the gate itself
    var threads = new Thread[ThreadCount];

    for (int t = 0; t < ThreadCount; t++)
    {
        threads[t] = new Thread(() =>
        {
            if (useGate)
            {
                ready.Signal();   // I've arrived
                gate.Wait();      // wait until everyone is released together
            }
            for (int i = 0; i < Iterations; i++)
                counter.Value++;
        });
    }

    foreach (var t in threads) t.Start();

    if (useGate)
    {
        ready.Wait();   // wait until every thread is waiting at the gate
        gate.Set();     // release them all at once
    }

    foreach (var t in threads) t.Join();
    return counter.Value;
}

class Counter
{
    public int Value;
}
```

**What to expect:** "Without gate" shows few failures, because the threads barely overlap. "With gate" shows many more.

Example results (12 cores, so 24 threads):

| Iterations | Without gate | With gate |
|---|---|---|
| 2,000 | 0/200 | 175/200 |
| 20,000 | 4/200 | 200/200 |

Without the gate, each thread's work finishes faster than the next thread starts, so it is almost a sequential program. With 10× more work, the threads start to overlap even without a gate, and the occasional failure appears.

**Try it:** change `Iterations` to `200` and then to `200_000`. Why does the gate matter less as the work per thread grows?

### 3. Assert an invariant and repeat (check-then-act)

A bank account checks the balance and then withdraws. Eight threads each try to withdraw the **whole** balance at the same time. The invariant is that **exactly one withdrawal succeeds and the balance ends at 0**.

```csharp
using System.Threading;

const int ThreadCount = 8;
const int Runs = 500;
const int StartBalance = 100;

int failures = 0, lowestBalance = StartBalance, mostSuccesses = 0;

for (int run = 0; run < Runs; run++)
{
    var account = new Account { Balance = StartBalance };
    int successes = 0;

    using var ready = new CountdownEvent(ThreadCount);
    using var gate = new ManualResetEventSlim(false);
    var threads = new Thread[ThreadCount];

    for (int t = 0; t < ThreadCount; t++)
    {
        threads[t] = new Thread(() =>
        {
            ready.Signal();
            gate.Wait();
            if (account.Withdraw(StartBalance))
                Interlocked.Increment(ref successes);   // safe count of how many succeeded
        });
    }

    foreach (var t in threads) t.Start();
    ready.Wait();
    gate.Set();
    foreach (var t in threads) t.Join();

    // The invariant
    bool ok = successes == 1 && account.Balance == 0;
    if (!ok) failures++;
    lowestBalance = Math.Min(lowestBalance, account.Balance);
    mostSuccesses = Math.Max(mostSuccesses, successes);
}

Console.WriteLine($"Invariant broken in {failures}/{Runs} runs");
Console.WriteLine($"Lowest balance seen: {lowestBalance}");
Console.WriteLine($"Most successful withdrawals in one run: {mostSuccesses} (should be 1)");

class Account
{
    public int Balance;

    public bool Withdraw(int amount)
    {
        if (Balance >= amount)        // check
        {
            Thread.Yield();           // widen the gap between check and act
            Balance -= amount;        // act
            return true;
        }
        return false;
    }
}
```

**What to expect:** a failure rate well above 0, negative balances, and runs where several withdrawals "succeeded".

**Try it:**

1. Remove `Thread.Yield()`. How does the failure rate change?
2. Make `Withdraw` thread-safe so the invariant holds in all 500 runs.

### 4. Release vs Debug (visibility bug)

A worker spins until another thread sets a flag. The flag is **not** `volatile`.

```csharp
using System.Threading;

var worker = new Worker();
var thread = new Thread(worker.Run) { IsBackground = true };   // background, so the app can still exit
thread.Start();

Thread.Sleep(1000);       // let the loop run long enough to be optimised
worker.Stop = true;
Console.WriteLine("Main: Stop = true");

bool finished = thread.Join(2000);
Console.WriteLine(finished
    ? "Worker saw the flag and stopped"
    : "Worker never saw the flag: it is stuck in the loop");

class Worker
{
    public bool Stop;   // not volatile

    public void Run()
    {
        while (!Stop) { }   // the JIT may read Stop once and never again
        Console.WriteLine("Worker: stopped");
    }
}
```

Run it both ways:

```
dotnet run                 # Debug: usually stops
dotnet run -c Release      # Release: may hang
```

**What to expect:** in Debug the worker stops. In Release the optimised JIT may **hoist** the read of `Stop` out of the loop, so the worker never sees the change.

If Release still stops, the JIT may not have optimised the loop yet. Turn off tiered compilation and try again:

```
DOTNET_TieredCompilation=0 dotnet run -c Release
```

Whether it hangs depends on the .NET version and the CPU, so treat this one as "may reproduce".

**Try it:** fix it with `volatile`, then with `Volatile.Read`, then with a `CancellationToken`. Check that Release always stops.

### 5. Force the exact interleaving (deterministic)

The counter has a **test hook** that runs between the read and the write. The program uses it to pause thread A after its read, let thread B finish, and then resume A. The update is lost on **every** run.

```csharp
using System.Threading;

const int Runs = 100;
int failures = 0;

for (int run = 0; run < Runs; run++)
{
    var counter = new HookedCounter();
    using var aHasRead = new ManualResetEventSlim(false);
    using var bIsDone = new ManualResetEventSlim(false);

    // The hook only pauses thread A
    counter.AfterRead = () =>
    {
        if (Thread.CurrentThread.Name == "A")
        {
            aHasRead.Set();   // tell B: I've read the value
            bIsDone.Wait();   // pause until B has finished
        }
    };

    var a = new Thread(counter.Increment) { Name = "A" };
    var b = new Thread(() =>
    {
        aHasRead.Wait();      // wait until A has read
        counter.Increment();  // B reads, adds and writes in full
        bIsDone.Set();        // let A continue with its stale value
    }) { Name = "B" };

    a.Start();
    b.Start();
    a.Join();
    b.Join();

    if (counter.Value != 2) failures++;
    if (run == 0) Console.WriteLine($"Run 1: expected 2, got {counter.Value}");
}

Console.WriteLine($"Lost update in {failures}/{Runs} runs");

class HookedCounter
{
    public int Value;
    public Action? AfterRead;   // test seam: null in production, so it does nothing

    public void Increment()
    {
        var temp = Value;       // read
        AfterRead?.Invoke();    // the test can pause here
        Value = temp + 1;       // write
    }
}
```

**What to expect:** `Lost update in 100/100 runs`, with no `Sleep` and no luck involved.

**Try it:** add a `lock` inside `Increment` around the read and write, keeping the hook between them. What happens when you run it, and why? (Hint: thread B can no longer get in while A is paused.) How would you change the test so it still finishes?

### 6. Deadlock with a barrier

Thread A locks X then Y. Thread B locks Y then X. A `Barrier` makes sure both hold their first lock before either reaches for the second.

```csharp
using System.Threading;

var lockX = new object();
var lockY = new object();
using var barrier = new Barrier(2);   // 2 participants: A and B

var a = new Thread(() =>
{
    lock (lockX)
    {
        Console.WriteLine("A: holds X");
        barrier.SignalAndWait();       // wait until B holds Y
        Console.WriteLine("A: wants Y");
        lock (lockY)
        {
            Console.WriteLine("A: got Y");
        }
    }
}) { IsBackground = true };

var b = new Thread(() =>
{
    lock (lockY)
    {
        Console.WriteLine("B: holds Y");
        barrier.SignalAndWait();       // wait until A holds X
        Console.WriteLine("B: wants X");
        lock (lockX)
        {
            Console.WriteLine("B: got X");
        }
    }
}) { IsBackground = true };

a.Start();
b.Start();

bool aFinished = a.Join(2000);
bool bFinished = b.Join(0);
Console.WriteLine(aFinished && bFinished
    ? "No deadlock"
    : "Deadlock: each thread holds one lock and waits forever for the other");
```

The threads are background threads, so the program can still exit after reporting the deadlock.

**What to expect:** `Deadlock` on every run.

**Try it:**

1. Comment out both `barrier.SignalAndWait()` lines and run it several times. How often does it deadlock now?
2. Fix it by making both threads take the locks in the **same order**.
3. Alternatively, try `Monitor.TryEnter(lockY, 500)` in place of the inner `lock`. What does the thread do when it times out?

### Quick reference

| Sample | Technique | Bug | Reproduces |
|---|---|---|---|
| 1 | Widen the race window | Lost update | Almost always |
| 2 | Starting gate | Lost update | More often than without |
| 3 | Invariant + repeat | Check-then-act | Measurable failure rate |
| 4 | Release build | Visibility | Depends on JIT/CPU |
| 5 | Test hook | Lost update | Every time |
| 6 | Barrier between locks | Deadlock | Every time |
