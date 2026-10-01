## C#
### Task vs ValueTask

#### [Back to C# contents](../_Contents.md)

*What is `Task` and what is `ValueTask`?*

Both represent **an asynchronous operation you can `await`**. The difference is about memory allocation and how you're allowed to use them.

### `Task` / `Task<T>`

- A **class** (reference type), so each new `Task` object is a heap allocation.
- Represents work that may finish later. You can `await` it, `await` it **again**, have **several** callers await it, call `Task.WhenAll`, store it and so on.
- It's the default return type for `async` methods: `async Task` or `async Task<T>`.

```csharp
public async Task<User> GetUserAsync(int id)
{
    return await _db.Users.FindAsync(id);
}
```

### `ValueTask` / `ValueTask<T>`

- A **struct** (value type). It wraps **one of the following**:
  1. **a result that's already available**: no allocation at all
  2. **a `Task<T>`**, when the work really is asynchronous
  3. **an `IValueTaskSource<T>`**, a reusable, pooled object (advanced, used by .NET internals like sockets)
- It exists to **avoid allocating a `Task` when the method usually finishes synchronously**.

The classic example is a cache:

```csharp
private readonly Dictionary<int, User> _cache = new();

public ValueTask<User> GetUserAsync(int id)
{
    if (_cache.TryGetValue(id, out var user))
        return new ValueTask<User>(user);          // hot path: result ready, NO allocation

    return new ValueTask<User>(LoadAndCacheAsync(id));   // slow path: wraps a real Task
}

private async Task<User> LoadAndCacheAsync(int id)
{
    var user = await _db.Users.FindAsync(id);
    _cache[id] = user;
    return user;
}
```

If 99% of calls hit the cache, returning `Task<User>` would allocate a new `Task` object on every call just to wrap a value you already have. `ValueTask<User>` avoids that. In a hot path called millions of times, that means less GC pressure.

### The rules for `ValueTask`

A `ValueTask` might be backed by a **pooled** object that gets reused after you consume it. So:

| ✅ Do | ❌ Don't |
|---|---|
| `await` it **once** | `await` the same `ValueTask` twice |
| | have multiple callers await it concurrently |
| | read `.Result` / `.GetAwaiter().GetResult()` before it has completed |
| Call `.AsTask()` if you need any of the above | store it in a field and reuse it later |

```csharp
var vt = GetUserAsync(1);
var a = await vt;
var b = await vt;           // ❌ undefined behaviour: the underlying source may already be reused

var t = GetUserAsync(1).AsTask();   // ✅ convert once, then use it like a normal Task
await Task.WhenAll(t, other);
```

### "Multiple callers await it concurrently": what it looks like

It means **the same `ValueTask` instance is awaited from two places at once**, e.g. two different methods or threads both waiting on it before it has finished.

**With `Task`, this is normal and a common pattern.** A typical case is deduplicating concurrent requests: if a load is already running, later callers wait on the same task instead of starting a new one.

```csharp
private Task<Config>? _loading;

public Task<Config> GetConfigAsync()
{
    _loading ??= LoadConfigFromDiskAsync();   // start once
    return _loading;                          // everyone gets the SAME Task
}

// Two callers at the same time:
var t1 = Task.Run(async () => await svc.GetConfigAsync());   // awaits _loading
var t2 = Task.Run(async () => await svc.GetConfigAsync());   // awaits _loading too, concurrently
await Task.WhenAll(t1, t2);                                  // ✅ fine
```

A `Task` can hold **many continuations** ("when I finish, resume caller 1, caller 2, …"), and it keeps its result forever. So any number of awaiters, at any time, is safe.

**The same pattern with `ValueTask` is broken:**

```csharp
private ValueTask<Config> _loading;           // ❌ storing a ValueTask to share it
private bool _started;

public ValueTask<Config> GetConfigAsync()
{
    if (!_started) { _loading = LoadConfigAsync(); _started = true; }
    return _loading;                          // both callers get the SAME ValueTask
}

// Caller A and caller B both do:  await svc.GetConfigAsync();   ← concurrently
```

Or more directly, in one method:

```csharp
ValueTask<int> vt = stream.ReadAsync(buffer);

var a = Task.Run(async () => await vt);   // awaiter #1
var b = Task.Run(async () => await vt);   // awaiter #2, while #1 is still waiting  ❌
```

**Why it breaks** depends on what the `ValueTask` wraps:

- **An `IValueTaskSource`** (pooled, e.g. from sockets or pipes): the source has **one slot** for a continuation. The second awaiter can overwrite the first one, so one caller never resumes (it hangs), or the call throws `InvalidOperationException`. After the first awaiter gets the result, the source goes **back to the pool** and may be reused for a *different* operation, so a late awaiter could even receive **someone else's result**.
- **A `Task`**: it happens to work, because `Task` supports multiple awaiters.
- **A ready result**: it also happens to work.

The problem is that **the caller can't know which one it received**, and the implementation may change between versions. So the contract says: treat every `ValueTask` as if it's the fragile pooled kind.

**The fix:** convert it to a `Task` once, then share that.

```csharp
private Task<Config>? _loading;

public Task<Config> GetConfigAsync()
{
    _loading ??= LoadConfigAsync().AsTask();   // ValueTask → Task, once
    return _loading;                           // ✅ safe to share and await concurrently
}
```

Rule of thumb: **a `ValueTask` is a one-shot receipt. One consumer awaits it once, right away. If it needs to be shared or stored, call `.AsTask()`.**

### Summary

| | `Task<T>` | `ValueTask<T>` |
|---|---|---|
| Kind | class (heap) | struct |
| Allocation if the result is already available | yes (except some cached results, e.g. `Task.CompletedTask`, `Task<bool>`) | **no** |
| Await multiple times / concurrently | ✅ | ❌ |
| `Task.WhenAll` / `WhenAny` | ✅ directly | only after `.AsTask()` |
| When to use | **default choice** | hot paths that **often complete synchronously**, when profiling shows allocations matter |

Where you'll see `ValueTask` in .NET itself:

- `Stream.ReadAsync(Memory<byte>)` returns `ValueTask<int>`, because data is often already buffered.
- `IAsyncDisposable.DisposeAsync()` returns `ValueTask`.
- `IAsyncEnumerator<T>.MoveNextAsync()` returns `ValueTask<bool>`, because most items in an `await foreach` are already available.

### Short answer

*"`Task` is a reference type representing an async operation. It's flexible and can be awaited many times. `ValueTask` is a struct that avoids allocating when the result is already available, which helps in hot paths that usually complete synchronously. But it must be awaited only once, and never concurrently. Use `Task` by default and `ValueTask` only when measurements justify it."*
