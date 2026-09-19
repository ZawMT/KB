# Rust — Intro

## Purpose

Rust gives C/C++-level performance and low-level control (no garbage collector, manual memory layout, zero-cost abstractions) while eliminating the memory-safety bugs (dangling pointers, data races, buffer overflows, use-after-free) that plague C/C++. It does this through its **ownership and borrowing** system, which enforces memory and thread safety at compile time rather than at runtime. Core pitch: "safe systems programming without a GC."

Typical use cases: OS/embedded/kernel work, browser engines (born at Mozilla for Servo), CLI tools, high-performance networking/backend services, WebAssembly, and increasingly game engines and blockchain/crypto infrastructure.

## Contemporaries / rivals — overall comparison

| Language | Primary domain | Memory management | Performance | Learning curve | Relation to Rust |
|---|---|---|---|---|---|
| **Rust** | Systems, embedded, backend/infra, WASM | Ownership/borrow checker (compile-time, no GC) | Very high (C-level) | Steep (borrow checker, lifetimes, traits) | — |
| C / C++ | Systems, embedded, performance-critical | Manual (malloc/free, RAII in C++) | Very high | Steep (manual memory mgmt, undefined behavior pitfalls) | Direct rival — the language(s) Rust set out to replace |
| Go | Backend/infra/CLI | Garbage collected | High, but GC pauses possible | Easy/fast to learn | Direct rival for backend/infra services; trades control for simplicity |
| Zig | Systems, embedded | Manual (explicit allocators) | Very high | Moderate (simpler than Rust, no borrow checker) | Direct rival — more direct C alternative, no compile-time safety guarantees |
| Swift | iOS/macOS app dev | ARC (automatic reference counting) | High | Moderate | Philosophical sibling (safety + modern syntax), not a domain rival — lives in mobile/Apple app dev |
| Kotlin | Android app dev (+ JVM backend) | Garbage collected (JVM) | Moderate–High | Easy–Moderate | Philosophical sibling, not a domain rival — lives in mobile/JVM app dev |

Rust's real head-to-head competition is **C / C++ / Zig** (systems) and **Go** (backend/infra). Swift and Kotlin share Rust's "safety-focused modern language" design philosophy but compete in mobile app development, a space Rust doesn't meaningfully occupy.

## Is it difficult to learn?

Yes — one of the steeper learning curves among mainstream languages, but the difficulty is concentrated, not spread evenly:

**Hard parts:**
- **Borrow checker** — enforces at compile time that data has exactly one owner (or one mutable reference / many immutable references) at a time. Coming from languages that allow free aliasing/mutation (C, Python, JS, Java, Go), expect weeks/months of "fighting the borrow checker."
- **Lifetimes** — annotation syntax (`'a`, `'static`) for how long references stay valid.
- **Trait system / generics** — more abstract than Java/Go interfaces; trait bounds, associated types, `dyn` vs generic dispatch take time.
- **Error handling ceremony** — `Result<T, E>`, `Option<T>`, `?` operator, `unwrap()` vs proper propagation — not conceptually hard, just more explicit/verbose than exceptions.

**Not especially hard:**
- Basic syntax is familiar if you know any C-like/curly-brace language.
- Tooling is excellent: `cargo` (builds/deps/tests) and `rustc`'s unusually good, actionable error messages.
- Well-documented standard library and ecosystem (`crates.io`).

**Realistic timeline:** 2–4 weeks to write simple programs comfortably; a few months before the ownership model feels natural instead of adversarial.

**Best on-ramp:** the official [Rust Book](https://doc.rust-lang.org/book/) (free) + `rustlings` (interactive exercises).

## Editions and "idioms"

Rust evolves via **Editions** (2015, 2018, 2021, 2024) — a mechanism that lets the language change without breaking existing code. A crate declares its edition in `Cargo.toml` (e.g. `edition = "2024"`), and crates with different editions can coexist and interoperate in the same dependency graph.

**"Idioms" ≠ "syntax."** They're related but not the same:
- **Syntax** = the literal grammar rules — what's legal to write.
- **Idioms** = the *conventional, preferred way* of writing something using the language's features — patterns experienced developers reach for by habit (e.g., `?` for error propagation, iterator chains over manual indexed loops), even when other legal ways exist.

An Edition can:
1. Add new **syntax** (new keywords/reserved words, new expression forms).
2. Change **default behavior** (e.g., closure capture rules, prelude auto-imports, pattern resolution).
3. Shift what's considered **idiomatic** — tooling (`rustfmt`, `clippy`) nudges toward new preferred patterns, superseding older ones from prior editions.

So "Rust 2024 Edition idioms" means: the conventional/preferred way to write code as recommended for the 2024 edition — new syntax plus new defaults and stylistic conventions the tooling now treats as "correct," not just a syntax version bump.

*(Context: this note assumes Rust 1.90.0 (released 2025-09-18) or later, with `edition = "2024"` in `Cargo.toml`.)*
