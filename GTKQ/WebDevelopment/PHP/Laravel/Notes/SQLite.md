# Viewing the SQLite Database

[Back](../README.md)

## What You Are Opening

With `DB_CONNECTION=sqlite` in `.env`, the entire database is **one file** —
`database/database.sqlite`. No server, no port, no username or password. Anything that can read
that file can read the database, which is why there are so many ways in.

The file is created by `php artisan migrate` (or by `laravel new` when you answer yes to the
migration question). It is listed in `database/.gitignore`, so it never reaches git — each
machine builds its own by running the migrations.

## Option 1 — `sqlite3` on the Command Line

macOS ships with the `sqlite3` client, so nothing needs installing. **Pass the filename as an
argument:**

```bash
cd testprj
sqlite3 database/database.sqlite
```

That gives a `sqlite>` prompt. Useful commands once inside:

```
.headers on          -- show column names in results
.mode column         -- align output into columns
.tables              -- list every table
.schema notes        -- the CREATE TABLE statement for one table
.schema              -- ... for all of them
.databases           -- full path of the file actually attached
.quit                -- leave (Ctrl+D also works)
```

`.headers on` and `.mode column` are worth typing first — raw output is bare pipe-separated text
that is hard to read.

Between those, plain SQL works as expected:

```sql
select * from migrations;
select count(*) from notes;
```

For a single query without entering the prompt at all:

```bash
sqlite3 database/database.sqlite "select * from migrations;"
```

That form is the handy one for scripts and for a quick look mid-debugging.

## Option 2 — `php artisan db`

Laravel reads `DB_CONNECTION` from `.env` and opens the right client for you, so the file path
never has to be typed:

```bash
php artisan db
```

The same command keeps working after a switch to MySQL or PostgreSQL — it follows the config
rather than the filename. Two related commands answer schema questions without any SQL:

```bash
php artisan db:show          # every table, with row counts
php artisan db:table notes   # columns, types and indexes of one table
```

## Option 3 — `php artisan tinker`

A REPL with the whole application booted, so the database is reachable **through Eloquent**
rather than through SQL:

```bash
php artisan tinker
```

```php
>>> App\Models\Note::all();
>>> App\Models\Note::count();
>>> App\Models\Note::create(['title' => 'First', 'body' => 'Trying Eloquent']);
>>> DB::select('select * from migrations');
```

This is the one to reach for when the question is about *models* — whether a relationship,
a cast or a scope behaves — and not merely whether the rows are there.

## Option 4 — A Graphical Tool

| Tool | Install |
| --- | --- |
| DB Browser for SQLite | `brew install --cask db-browser-for-sqlite` |
| TablePlus | `brew install --cask tableplus` |
| VS Code — *SQLite Viewer* extension | from the Extensions panel |

The first is free and purpose-built; TablePlus is nicer to use and its free tier covers SQLite.
The VS Code extension turns a click on `database.sqlite` in the file tree into a browsable tab,
without leaving the editor the project is already open in.

All of them open the file directly — there are no credentials to enter.

## Things That Bite Beginners

**`sqlite3` with no filename opens a scratch database.** It says so, and the message is easy to
skim past:

```
Connected to a transient in-memory database.
Use ".open FILENAME" to reopen on a persistent database.
```

Every query then runs against an empty database that disappears on exit — `.tables` prints
nothing and it looks like the project has no tables. Either quit and restart with the filename,
or attach the real file from the prompt:

```
sqlite> .open database/database.sqlite
```

**A missing filename is created, not reported.** `.open wrong-name.sqlite` silently makes a new,
empty database instead of raising an error. So an unexpectedly blank `.tables` usually means a
wrong path rather than an empty project — check with `.databases`, which prints the full path of
what is really attached.

**A missing dot turns a command into SQL.** Any line *not* starting with `.` is treated as SQL,
and SQL only ends at a semicolon. Typing `tables` instead of `.tables` leaves the statement
unfinished, and the prompt changes to `...>`:

```
sqlite> tables
   ...> .quit
   ...> .tables
   ...>
```

Dot-commands are only recognised at the **start of a fresh statement**, so from here even
`.quit` is swallowed as more SQL text and nothing responds. Escape by typing a lone semicolon and
pressing Enter — SQLite parses the accumulated text, reports a syntax error, and returns a clean
`sqlite>` prompt. `Ctrl+C` abandons the buffer just as well.

| Input | Treated as | Runs when |
| --- | --- | --- |
| Starts with `.` | A sqlite3 client command | Immediately, on Enter |
| Anything else | SQL | Only once a `;` arrives |

**Shell and editor habits do not apply.** `ls` and `clear` are shell commands and mean nothing
inside the client (`.shell ls` runs one if needed); `:exit` is vim. Leaving is `.quit`, `.exit`
or `Ctrl+D`.

**Avoid two writers at once.** Browsing in a GUI while `php artisan serve` runs is fine, but
SQLite locks the *whole file* for a write. Leaving an uncommitted edit open in a GUI makes the
application fail with `database is locked`.

## Reading the `migrations` Table

The most common reason to open the file at all is to see what has actually been applied:

```bash
sqlite3 database/database.sqlite "select * from migrations;"
```

```
1|0001_01_01_000000_create_users_table|1
2|0001_01_01_000001_create_cache_table|1
3|0001_01_01_000002_create_jobs_table|1
```

The last column is the **batch** number. Comparing this list against the files in
`database/migrations/` shows what is still pending — which is exactly what
`php artisan migrate:status` reports in a friendlier layout. See
[DB Migration](./DBMigration.md) for what those batches mean.
