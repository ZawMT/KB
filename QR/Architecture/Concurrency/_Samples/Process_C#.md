## Concurrency
### Processes in C# (Basics)

#### [Back to Concurrency contents](../_Contents.md)

*How do you start and manage another process from C#?*

Short answer: **Use `System.Diagnostics.Process`. Start a program with `Process.Start(...)`, configure it with `ProcessStartInfo` (arguments, output capture), wait with `WaitForExitAsync()`, and check `ExitCode`.**

> Each sample is a complete console program (top-level statements, .NET 6+). Paste one into `Program.cs` and run it with `dotnet run`.
>
> The samples run `dotnet` as the child program, since it exists on any machine that can run them.

### Key idea: a process has its own memory

Unlike threads, a child process **shares no variables** with your program. You communicate through:

- **Arguments** going in
- **Standard output / error** coming out
- The **exit code** (by convention, `0` = success)
- Files, pipes, sockets, etc. for anything more

### 1. Start a process and wait for it

```csharp
using System.Diagnostics;

using Process p = Process.Start("dotnet", "--info");   // output goes straight to the console
await p.WaitForExitAsync();   // async wait; p.WaitForExit() is the blocking version
Console.WriteLine($"Exit code: {p.ExitCode}");
```

`Process` holds OS resources, so dispose of it (`using`).

### 2. Capture the output

```csharp
using System.Diagnostics;

var psi = new ProcessStartInfo
{
    FileName = "dotnet",
    Arguments = "--version",
    RedirectStandardOutput = true,   // capture output instead of printing it
    UseShellExecute = false          // required for redirection
};

using Process p = Process.Start(psi)!;

string output = await p.StandardOutput.ReadToEndAsync();
await p.WaitForExitAsync();

Console.WriteLine($".NET SDK version: {output.Trim()}");
Console.WriteLine($"Exit code: {p.ExitCode}");
```

### 3. Capture output and errors together

```csharp
using System.Diagnostics;

var psi = new ProcessStartInfo
{
    FileName = "dotnet",
    RedirectStandardOutput = true,
    RedirectStandardError = true,
    UseShellExecute = false
};
psi.ArgumentList.Add("no-such-command");   // ArgumentList handles quoting/spaces for you

using Process p = Process.Start(psi)!;

// Read both streams at the same time
Task<string> stdout = p.StandardOutput.ReadToEndAsync();
Task<string> stderr = p.StandardError.ReadToEndAsync();
await p.WaitForExitAsync();

Console.WriteLine($"Exit code: {p.ExitCode}");   // non-zero = failure
Console.WriteLine($"Output: {await stdout}");
Console.WriteLine($"Errors: {await stderr}");
```

> ⚠️ Don't read one stream to the end and **then** the other. If the child fills the buffer of the stream you aren't reading, it blocks, and both programs wait forever (**deadlock**). Read both concurrently, as above.

### 4. Info about the current process

```csharp
using System.Diagnostics;

using Process me = Process.GetCurrentProcess();

Console.WriteLine($"Name:    {me.ProcessName}");
Console.WriteLine($"ID:      {Environment.ProcessId}");
Console.WriteLine($"Threads: {me.Threads.Count}");   // even a "simple" app has several
Console.WriteLine($"Memory:  {me.WorkingSet64 / 1024 / 1024} MB");
Console.WriteLine($"Started: {me.StartTime}");
```

The thread count shows the hierarchy from [Entities](../_Notes/Concurrency_03_Entities.md): one process contains several threads (main thread, GC, thread pool, etc.).

### 5. List running processes

```csharp
using System.Diagnostics;

foreach (Process p in Process.GetProcesses().OrderBy(p => p.ProcessName).Take(15))
{
    Console.WriteLine($"{p.Id,7}  {p.ProcessName}");
    p.Dispose();
}
```

`Process.GetProcessesByName("name")` finds processes by name (without `.exe`).

### 6. Timeout and kill

```csharp
using System.Diagnostics;

// A command that runs for ~10 seconds
var psi = OperatingSystem.IsWindows()
    ? new ProcessStartInfo("ping", "-n 10 127.0.0.1")
    : new ProcessStartInfo("ping", "-c 10 127.0.0.1");
psi.RedirectStandardOutput = true;   // keep the console quiet
psi.UseShellExecute = false;

using Process p = Process.Start(psi)!;
_ = p.StandardOutput.ReadToEndAsync();   // drain output so the child never blocks

using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(2));
try
{
    await p.WaitForExitAsync(cts.Token);
    Console.WriteLine("Finished in time");
}
catch (OperationCanceledException)
{
    p.Kill(entireProcessTree: true);   // also kills any children it started
    Console.WriteLine("Timed out: process killed");
}
```

### 7. Open a file or URL with the default app

```csharp
using System.Diagnostics;

Process.Start(new ProcessStartInfo
{
    FileName = "https://learn.microsoft.com/dotnet",
    UseShellExecute = true   // let the OS pick the app (browser, editor...)
});
```

| `UseShellExecute` | Meaning |
|---|---|
| `false` (default in .NET Core / .NET 5+) | Run the executable directly; can redirect input/output |
| `true` | Ask the OS shell to open it, like double-clicking; can't redirect |

### Quick reference

| Member | What it does |
|---|---|
| `Process.Start(file, args)` | Start a program |
| `ProcessStartInfo` | Configure: `FileName`, `Arguments`/`ArgumentList`, `WorkingDirectory`, redirection |
| `RedirectStandardOutput/Error/Input` | Capture or feed the child's streams |
| `StandardOutput.ReadToEndAsync()` | Read everything the child printed |
| `WaitForExitAsync()` / `WaitForExit()` | Wait for the process to end (async / blocking) |
| `ExitCode` | Result code (`0` = success by convention) |
| `HasExited` | Has it finished? |
| `Kill(entireProcessTree: true)` | Force-stop it (and its children) |
| `Process.GetCurrentProcess()` | Your own process |
| `Process.GetProcesses()` / `GetProcessesByName()` | Find running processes |

### Process vs Thread vs Task

| | Process | Thread | Task |
|---|---|---|---|
| Memory | **Own** (isolated) | Shared with its process | Shared (runs on threads) |
| Cost to create | Highest | Medium (~1 MB stack) | Lowest (reuses pool threads) |
| Communicate via | Args, stdout, exit code, IPC | Shared variables + locks | Return values (`Task<T>`) |
| A crash affects | Only itself | The whole process | Stored in the task; rethrown on `await` |
| Typical use | Run other programs, isolation | Low-level / long-running work | Everyday async and parallel work |

See [Thread (C#)](Thread_C%23.md) and [Task (C#)](Task_C%23.md) for the other two.
