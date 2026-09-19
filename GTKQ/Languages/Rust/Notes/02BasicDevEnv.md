# Rust — Basic Dev Environment

## Installing the toolchain

Rust is installed via **rustup**, the official toolchain installer/manager (handles `rustc`, `cargo`, `clippy`, `rustfmt`, and version/target switching):

```bash
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source "$HOME/.cargo/env"   # or restart the shell
```

This installs:
- `rustc` — the compiler.
- `cargo` — build tool / package manager / project scaffolding.

## Two ways to run a `.rs` file

### 1. Direct compile with `rustc` (no project needed)

Fine for a single loose file with no dependencies:

```bash
rustc main.rs && ./main
```

- Compiles `main.rs` straight to a native executable, placed next to the source by default.
- `-o <path>` overrides where the binary is written, e.g. `rustc main.rs -o /tmp/main` — useful when compiling from a different working directory or to avoid dropping the binary inside the repo.

### 2. Cargo project (the idiomatic way, for anything beyond a toy file)

```bash
cargo new my_project   # or `cargo init` in an existing folder
cargo run               # builds + runs
```

- All build output goes into a `target/` directory — one line in `.gitignore` (`target/`) keeps compiled artifacts out of git entirely.
- Needed as soon as the project has external dependencies (crates), since `rustc` alone doesn't manage dependencies.
- To try: convert the `01/main.rs` experiment into a Cargo project as a next step.

## The generated binary — is it an object file?

**No.** `rustc main.rs` produces a fully linked, standalone **executable**, not an object file.

| | Object file (`.o`) | Executable (what `rustc` outputs by default) |
|---|---|---|
| Stage | Intermediate — one compiled-but-unlinked translation unit | Final — compiled *and* linked |
| Can it run on its own? | No — needs linking (with the runtime, other object files, libs) | Yes — directly runnable |
| Produced by | `rustc --emit=obj` (opt-in, rarely needed manually) | `rustc` default behavior |

So the `main` file created by `rustc main.rs` is already the finished program. It has no file extension (macOS/Linux don't use extensions to mark something runnable — see below) but does have the **executable permission bit** set (`-rwxr-xr-x` in `ls -l`), which is what actually lets `./main` run it.

## No file extension on the binary

- macOS/Linux determine "is this runnable" from the file's **permission bits** (the `x` flag) or a `#!` shebang for scripts — not from a filename suffix.
- Windows is the opposite: it relies on extensions, so `rustc main.rs` on Windows produces `main.exe`.
- Practical consequence: a generic `.gitignore` pattern can't target "extensionless files" as a category. Options, in order of preference:
  1. Use Cargo and ignore `target/` (see above) — sidesteps the problem entirely.
  2. Ignore by explicit path, e.g. `GTKQ/Languages/Rust/*/main`, if the binary is always named `main`.
  3. Manually `rm`/avoid `git add`-ing the binary before committing.
