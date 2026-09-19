# Rust — Cargo

## What it is

Cargo is Rust's official **build tool and package manager** — the "build tool" component of a [toolchain](../../_KeyPoints.md). It replaces manually running `rustc` once a project grows past a single dependency-free file.

## What it handles

| Task | Command |
|---|---|
| Create a new project | `cargo new my_project` (with git init + starter files) or `cargo init` in an existing folder |
| Compile | `cargo build` (debug) / `cargo build --release` (optimized) |
| Compile + run in one step | `cargo run` |
| Add a dependency | edit `Cargo.toml`, or `cargo add <crate>` |
| Download/resolve dependencies | `cargo build` does this automatically, using `Cargo.lock` to pin exact versions |
| Run tests | `cargo test` |
| Check for errors without producing a binary (fast) | `cargo check` |
| Format code | `cargo fmt` |
| Lint | `cargo clippy` |
| Publish a package to crates.io | `cargo publish` |

## Key files it manages

- `Cargo.toml` — the project manifest (name, version, dependencies, edition).
- `Cargo.lock` — exact resolved dependency versions, for reproducible builds. Auto-generated on first build — never hand-written. Commit for binaries, not for libraries.
- `src/main.rs` (binary) or `src/lib.rs` (library) — where the code goes.
- `target/` — all build output. `cargo new` auto-generates a `.gitignore` that excludes it.

## `rustc` vs Cargo

| | `rustc` | Cargo |
|---|---|---|
| Scope | Compiles one file | Orchestrates an entire project |
| Dependencies | None — no concept of external crates | Manages them via `Cargo.toml` + `Cargo.lock` |
| Output location | Next to source by default, or `-o <path>` | `target/debug/` or `target/release/` |
| When to use | Single dependency-free file (see [02BasicDevEnv](./02BasicDevEnv.md)) | Anything with dependencies, multiple files, or tests |

## Useful `cargo new` parameters

`cargo new <path>` uses `<path>` as both the directory to create and (by default) the package name — but a few flags decouple or adjust that:

| Flag | Purpose |
|---|---|
| `--name <name>` | Overrides the package name written into `Cargo.toml`, independent of the directory name. **Needed when the directory name isn't a valid crate identifier** — e.g. Rust package/crate names can't start with a digit, so `cargo new 03` fails, but `cargo new 03 --name guess_number` works: folder stays `03`, package is named `guess_number`. |
| `--vcs <none\|git\|hg\|...>` | Controls whether/which version control gets initialized inside the new folder. Default is `git`, which creates a **nested** `.git` — a problem if you're already inside a git repo (like this KB repo). Use `--vcs none` to skip it and just get the `.gitignore` file without a nested repo. |
| `--lib` | Scaffolds a library (`src/lib.rs`) instead of a binary (`src/main.rs`). |
| `--bin` | Explicitly scaffolds a binary — this is the default, so rarely needed. |

Example used for project `03` in this KB (numeric folder name + already-in-a-repo):

```bash
cargo new 03 --name guess_number --vcs none
```

**Alternative used for `02`:** create the folder manually first (`mkdir 02`), `cd` into it, and run `cargo init` instead of `cargo new`. `cargo init` scaffolds into an *existing* directory rather than creating one — same digit-prefix and nested-`.git` caveats apply, so `cargo init --name guess_number --vcs none` from inside `02/` achieves the same result as the `cargo new` command above.

## Minimum file footprint for a small project

For a small single-purpose program (e.g. a CLI number-guessing game using the `rand` crate), one source file is enough — splitting into modules is only worth it once there are multiple distinct concerns (e.g. a to-do app with a task struct, file storage, and CLI parsing as separate pieces).

`cargo new test_app` scaffolds:

```
test_app/
├── Cargo.toml       ← manifest: name, version, edition, [dependencies]
├── .gitignore        ← auto-generated, ignores target/
└── src/
    └── main.rs        ← game logic goes here
```

Plus `Cargo.lock`, generated automatically on first build.

So: **1 file actually written** (`src/main.rs`), **1 file edited but not coded in** (`Cargo.toml`, to add `rand`), and the rest is scaffolding.
