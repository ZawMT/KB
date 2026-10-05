## Concurrency
### Named Semaphores

#### [Back to Concurrency contents](../_Contents.md)

*What is a named semaphore, can its name collide with other processes, and when would you open an existing one on purpose?*

Short answer: **a named semaphore is owned by the operating system, not by your process. Any process on the machine that uses the same name gets the same semaphore. That is how processes share it, and also why names can collide.**

### Unnamed vs named

| | Unnamed semaphore | Named semaphore |
|---|---|---|
| Visible to | Only the process that created it | Every process on the machine (within its scope) |
| Used for | Limiting threads inside one app | Limiting work **across processes** |
| .NET types | `SemaphoreSlim`, or `Semaphore` without a name | `Semaphore` with a name |

```csharp
// Unnamed: only this process can use it
var local = new SemaphoreSlim(3, 3);

// Named: any process that uses "MyCompany.MyApp.Jobs" shares it
var shared = new Semaphore(3, 3, "MyCompany.MyApp.Jobs");
```

### Can the name collide with other processes?

Yes. The OS doesn't know which app a name "belongs" to. If another program, even an unrelated one, uses the same name, both programs share one semaphore.

```csharp
var sem = new Semaphore(initialCount: 3, maximumCount: 3,
                        name: "MyLock", out bool createdNew);
```

- If no semaphore called `"MyLock"` exists, a new one is created and `createdNew == true`.
- If one already exists, you **silently open that one**, and `createdNew == false`.
  - Your `initialCount` and `maximumCount` are **ignored**. You get whatever count the other process set up.
  - The other app's threads and yours now block each other, which causes unexpected delays or deadlocks.

### How to avoid collisions

1. **Use a unique name.** Add a company/app prefix, and a GUID if needed:
   ```csharp
   "MyCompany.MyApp.ImportJob.7c9e6679-7425-40de-944b-e07fc1f90ae7"
   ```
2. **Check `createdNew`.** If you expected to be the first and you weren't, log it or fail.
3. **Choose the scope on purpose (Windows):**
   - With no prefix (or `Local\`), the name is visible only within the current user's login session.
   - `Global\` makes it visible across all sessions (needed for Windows services). The chance of a collision is higher.
4. **Restrict access (Windows).** A malicious process could create the name first ("name squatting") to block or interfere with your app. Windows lets you set access permissions on the semaphore to stop that.

### Opening an existing semaphore on purpose

Developers **do** reuse existing named semaphores intentionally, but almost always ones that **they or a cooperating program created**.

**1. Your own processes sharing a limit (the main use)**

Several copies of your app, or a service and its worker processes, share a rule such as "at most 3 can use the GPU / licence server / file at once". One process creates the semaphore, and the others open it:

```csharp
// Creator (for example, the main service)
var sem = new Semaphore(3, 3, @"Global\MyCompany.MyApp.GpuSlots");

// Other processes: open it, never create it
if (Semaphore.TryOpenExisting(@"Global\MyCompany.MyApp.GpuSlots", out var existing))
{
    existing.WaitOne();
    try { /* use the GPU */ }
    finally { existing.Release(); }
}
```

`OpenExisting` / `TryOpenExisting` make the intent explicit: "this should already exist, and I don't want to create it by accident."

**2. A documented contract between different products**

A vendor might document "our tool holds the semaphore `X` while it's writing the file", so that other programs can coordinate with it. This is rare, and it only works because the name is a published, agreed interface.

**3. Diagnostics and tooling**

Tools such as Sysinternals **WinObj** and **Process Explorer** list named kernel objects, so you can see who created what. This is for inspecting and debugging, not for joining them in production code.

**4. Security (a related case that uses mutexes)**

Some malware creates a named mutex as an "already infected" marker. Security tools sometimes create the same name first, so the malware thinks the machine is already infected and stops. It's the same idea of using someone else's name deliberately, but the objects are usually mutexes, not semaphores.

### What about semaphores created by Windows or .NET?

Windows and .NET **don't publish** any named semaphores for applications to use. The ones they create internally are undocumented implementation details and can change between versions, so don't depend on them.

| Whose semaphore? | Reuse it on purpose? |
|---|---|
| Created by your own app or services | Yes. This is the main reason names exist |
| Documented by another vendor | Yes, but it's rare |
| Internal to Windows or .NET | No. It's undocumented and may change |
| An unknown app's | No. That's a collision, not a feature |

### Platform support in .NET

- **Named `Semaphore` is supported only on Windows.** On Linux and macOS, passing a name throws `PlatformNotSupportedException`.
- **Named `Mutex`** works on all platforms.
- `SemaphoreSlim` can never be named. It always belongs to a single process.

### Summary

- A named semaphore is an **OS-level object** shared by every process that uses the same name.
- Name collisions are possible. When one happens, you **silently open the existing semaphore**, and your counts are ignored.
- Avoid collisions with **unique names**, by **checking `createdNew`**, by **choosing `Local\` or `Global\`** deliberately, and with **access permissions**.
- Opening an existing semaphore on purpose (`OpenExisting` / `TryOpenExisting`) is normal **between your own processes**, or with a **documented** name from another vendor.
- Don't rely on undocumented semaphores inside Windows or .NET.
- In .NET, named `Semaphore` works **only on Windows**.

### Think about it

- Process A creates `"MyLock"` with a count of 3. Process B then calls `new Semaphore(5, 5, "MyLock")`. How many threads across both processes can enter at once?
- A process takes a permit from a named semaphore and then crashes before calling `Release()`. What happens to that permit? How is this different from a named mutex, which Windows marks as "abandoned"?
- Why might a Windows service and a desktop app fail to share a semaphore named `"MyCompany.MyApp.Jobs"`, even though the names match?
