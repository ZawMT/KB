# Rust — Traits

## What it is

A trait declares a set of methods a type must implement — closest analog is a C# **interface**. Code can be written generically against "anything implementing this trait," the same way you'd write against `ISomething` in C#.

## How it's used

```rust
trait Greet {
    fn greet(&self) -> String;
}

struct Person {
    name: String,
}

impl Greet for Person {
    fn greet(&self) -> String {
        format!("Hello, {}!", self.name)
    }
}
```

The `impl TraitName for TypeName { ... }` block is separate from the struct definition — you satisfy the trait outside the struct itself, not inside it.

## Differences from a C# interface

| | C# interface | Rust trait |
|---|---|---|
| Default method bodies | Only with default interface methods (C# 8+) | Built-in, common pattern |
| Where the implementation lives | Inside the class (or via extension methods, as a special case) | Always in a separate `impl Trait for Type` block — the normal way, not a special case |
| Auto-generating boilerplate | No direct equivalent | `#[derive(...)]` — see below |

## `derive`

`derive` auto-generates a trait implementation instead of writing the `impl` block by hand. It only works for traits that support it (mechanical, field-by-field logic).

```rust
#[derive(Debug, Clone)]
struct Task {
    id: u32,
    description: String,
    done: bool,
}
```

This expands (conceptually) into:
```rust
impl std::fmt::Debug for Task { /* auto-generated printing logic */ }
impl Clone for Task { /* auto-generated field-by-field copy logic */ }
```
— but the compiler writes it, not you.

**C# comparison:** closest thing is what `record` types give you for free (auto `Equals`, `GetHashCode`, `ToString`) — except in Rust it's opt-in per-trait via `#[derive(...)]`, and extensible to any trait that supports it, not just a fixed set.

`Debug` and `Clone` are built into the standard library. Other traits' derive macros come from external crates — e.g. `Serialize`/`Deserialize` (for converting a struct to/from JSON) come from the `serde` crate, and only become available once `serde` is added as a dependency with its `derive` feature enabled.
