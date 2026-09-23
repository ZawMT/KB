# Rust — Modules, `use`, and Visibility

## `mod` vs `use`

These do two different jobs:

| | Purpose |
|---|---|
| `mod name;` | Tells the compiler "compile `name.rs` and include it as part of this crate." Without it, the file isn't compiled at all — just a stray file Cargo ignores. |
| `use path::Item;` | Brings `Item` into the current scope so it can be referenced by its short name instead of the full path. |

**C# comparison:** `mod` is like adding a file to your project so its code gets compiled at all. `use` is like a `using MyNamespace;` directive — it lets you write `MyClass` instead of `MyNamespace.MyClass`.

`mod name;` must live in the *parent* that includes the file (e.g. in `main.rs`, pointing at `task.rs`) — not inside `task.rs` itself. Putting `mod task;` inside `task.rs` would make Rust look for a nested `src/task/task.rs`.

## What `use` actually imports

`use path::to::Item;` brings whatever `Item` is (module, function, struct, anything) into scope by its **short name** — specifically the *last segment* of the path, not any segment along the way.

```rust
mod storage;          // storage.rs is compiled and part of the crate

use storage;           // brings the name `storage` into scope (redundant here —
                        // storage is already directly visible in this file
                        // because `mod storage;` is declared right here)

storage::load_tasks(); // fully qualified call — no `use` needed for this to work

use storage::load_tasks;  // brings the FUNCTION itself into scope
load_tasks();              // now callable unqualified
```

So `use storage;` only shortens references to the module name itself — it does **not** let you drop the `storage::` prefix from function calls. To call `load_tasks()` bare, `use` the function (or the type, struct, etc.) directly:

```rust
use storage::{load_tasks, save_tasks};
```

## Convention: qualified calls vs `use`

Not a compiler rule — a style choice, and either works for both functions and types:

- **Types** (e.g. `Task`) are usually brought in with `use`, since they're referenced constantly and in many places (`Vec<Task>`, struct literals, pattern matches) — spelling out `task::Task` everywhere gets noisy.
- **Functions** are often called fully qualified (`storage::load_tasks()`) even when a `use` would work, because the qualified path makes it obvious at the call site where the behavior comes from — a small readability aid.

## `pub` visibility

Struct visibility and field visibility are **independent** — marking one `pub` does not imply the other.

```rust
pub struct Task {
    pub id: u32,
    pub description: String,
    pub completed: bool,
}
```

- `pub struct Task` — makes the *type itself* visible/importable outside the module. Without this, other modules can't even name `task::Task`.
- `pub` on each field — makes that specific field accessible from outside the module. Without it, external code can see the struct exists but can't read/write that field directly.

**C# comparison:** same rule as `public class` not making its members public — each member still needs its own `public` modifier.

**Design note:** fields don't have to be `pub`. Keeping them private and exposing only a `pub fn new(...)` constructor plus getter/setter methods is a common encapsulation pattern — it lets you control how a field is mutated instead of allowing direct external writes. For a small project, public fields (as above) are simpler and perfectly reasonable.
