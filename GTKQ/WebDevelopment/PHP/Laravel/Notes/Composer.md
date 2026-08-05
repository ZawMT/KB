# Composer

Composer is PHP's dependency manager — the equivalent of npm for Node. Laravel is installed
through it, and every Laravel package is added through it.

## `require` vs `global require`

This is the distinction that prompted the note.

| | `composer require X` | `composer global require X` |
|---|---|---|
| Installs into | The current project's `vendor/` | `~/.composer/vendor/` |
| Recorded in | That project's `composer.json` | Nothing project-specific |
| Scope | This project only | The whole user account |
| Used for | Libraries the app depends on | Command-line tools |
| npm equivalent | `npm install` | `npm install -g` |

So `composer global require` is not "install for all my projects" — it is specifically how
**CLI tools** written in PHP get installed. The tool's executable is placed in:

```bash
composer global config bin-dir --absolute
# → /Users/zaw/.composer/vendor/bin
```

### The PATH gotcha

That `bin` folder is **not** on the PATH by default on macOS. So a global install can appear to
fail:

```bash
composer global require laravel/installer
laravel new myapp
# → zsh: command not found: laravel
```

The package installed fine; the shell just cannot find it. Fix in `~/.zshrc`:

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

## `laravel/installer` — what it is, and why it is optional

`laravel/installer` is one of those CLI tools. It provides the `laravel` command, essentially
just `laravel new`. It is a convenience wrapper — **not** a requirement for using Laravel.

Two ways to start a project, same end result:

```bash
# Needs nothing beyond Composer itself
composer create-project laravel/laravel .

# Needs the global install above, plus the PATH fix
laravel new myapp
```

| | `composer create-project` | `laravel new` |
|---|---|---|
| Setup needed | None | Global install + PATH |
| Prompts | None — plain skeleton | Asks about starter kit, test framework, database |
| Extras | Just the code | Can also `git init`, `npm install`, run migrations |

`create-project` is the shorter path when the PATH is not already set up. The installer is worth
having once you are creating projects often and want its prompts.

## The Files Composer Manages

| File / folder | What it is | In git? |
|---|---|---|
| `composer.json` | The packages you asked for, as version *ranges* (`^12.0`) | **Yes** |
| `composer.lock` | The exact versions actually resolved | **Yes** |
| `vendor/` | The downloaded code itself | **No** — Laravel's own `.gitignore` excludes it |

Committing `composer.lock` is what makes an install reproducible: everyone, and the production
server, gets byte-identical dependencies rather than "whatever matched the range today."

## `install` vs `update`

Worth being clear about, because mixing them up causes surprise upgrades:

```bash
composer install    # Install exactly what composer.lock says. Does not change versions.
composer update     # Re-resolve within composer.json's ranges, then REWRITE composer.lock.
```

- `install` — what you run after cloning a project, and what runs on a deploy.
- `update` — a deliberate act of upgrading, done on your own machine, reviewed, then committed.

Running `update` on a server is a common way to deploy code nobody tested.

## Production Flags

These appear in [03](../03/README.md) and [04](../04/README.md):

```bash
composer install --no-dev --optimize-autoloader
```

- `--no-dev` — skip `require-dev` packages (PHPUnit, Faker, debug tooling). Smaller, and keeps
  development tools off a public server.
- `--optimize-autoloader` — build a full classmap up front, so PHP resolves class names by lookup
  instead of scanning directories on every request.
- `--classmap-authoritative` — goes further: the classmap is treated as complete, so PHP never
  falls back to filesystem scanning. Correct for an immutable container image (used in
  [04](../04/Dockerfile)); wrong anywhere classes are generated at runtime.

## Everyday Commands

```bash
composer create-project laravel/laravel .   # start a new project in the current folder
composer install                            # install from composer.lock
composer update                             # upgrade within composer.json ranges
composer require vendor/package             # add a dependency
composer require --dev vendor/package       # add a dev-only dependency
composer remove vendor/package              # drop one
composer dump-autoload                      # rebuild the autoloader after adding classes
composer show                               # list installed packages
composer outdated                           # what has newer versions available
composer why vendor/package                 # explain what pulled a package in
```

## Environment Checked On (5 Aug 2026)

```
PHP      8.5.8
Composer 2.10.2
```

Extensions already present and required by Laravel: `mbstring`, `openssl`, `tokenizer`, `xml`,
`ctype`, `curl`, `fileinfo`, plus `pdo_sqlite` and `sqlite3` for the default SQLite database.
Nothing from the [01](../01/README.md) prerequisites section needed installing.
