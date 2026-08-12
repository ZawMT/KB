# Database Migrations — `up()` and `down()`

[Back](../README.md)

## What a Migration Is

A migration is **version control for your database schema**. Instead of opening phpMyAdmin and
clicking "create table", you write the schema as PHP code, commit it to git, and every
teammate and server runs the same commands to end up with an identical database.

Each file is a single, ordered change: create a table, add a column, add an index. The
timestamp in the filename decides the order — Laravel runs them oldest-first, which is why
`0001_01_01_000000_create_users_table.php` always runs before
`2026_08_11_224210_create_notes_table.php`.

Migrations live in `database/migrations/`.

## Anatomy of a Migration

Here is the whole file that `php artisan make:migration create_notes_table` produced:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
```

Note the `return new class extends Migration` — it is an **anonymous class**, not a named one.
That is why two migrations can both be "about notes" without colliding: neither has a class name
to clash over. Older Laravel versions used named classes; you may still see those in tutorials.

## `up()` — Apply the Change

`up()` holds what should happen when the migration runs:

```php
public function up(): void
{
    Schema::create('notes', function (Blueprint $table) {
        $table->id();             // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, named "id"
        $table->string('title');  // VARCHAR(255) NOT NULL
        $table->text('body');     // TEXT NOT NULL
        $table->timestamps();     // created_at + updated_at, both nullable TIMESTAMP
    });
}
```

`$table` is a **Blueprint** — a fluent builder. Each method call adds one column definition, and
Laravel compiles the whole thing into a `CREATE TABLE ...` statement for whichever driver you are
on (SQLite, MySQL, PostgreSQL). That is the point of the abstraction: the same PHP, a different
SQL dialect per database.

## `down()` — Undo the Change

`down()` must reverse exactly what `up()` did. They are mirror images:

```php
public function down(): void
{
    Schema::dropIfExists('notes');   // up() created the table, so down() drops it
}
```

If `up()` **adds** a column, `down()` **drops** that same column:

```php
public function up(): void
{
    Schema::table('notes', function (Blueprint $table) {
        $table->boolean('is_pinned')->default(false);
    });
}

public function down(): void
{
    Schema::table('notes', function (Blueprint $table) {
        $table->dropColumn('is_pinned');
    });
}
```

## The Commands

| Command | What it does |
| --- | --- |
| `php artisan migrate` | Runs `up()` on every migration not yet run |
| `php artisan migrate:rollback` | Runs `down()` on the last *batch* |
| `php artisan migrate:rollback --step=1` | Rolls back one migration only |
| `php artisan migrate:fresh` | Drops **all** tables, then re-runs everything (skips `down()`) |
| `php artisan migrate:refresh` | Rolls back everything via `down()`, then re-migrates |
| `php artisan migrate:status` | Shows which migrations have run |
| `php artisan make:migration <name>` | Creates a new migration file |

Laravel tracks what has already run in a `migrations` table holding the filename and a **batch**
number. Everything applied in one `migrate` call shares a batch number — and that batch is the
unit `rollback` reverses.

## Things That Bite Beginners

**Never edit a migration that has already run in production.** It will not re-run — Laravel sees
it listed in the `migrations` table and skips it. Write a *new* migration for the change instead.
Editing the file and running `migrate:fresh` is fine locally while learning, but it destroys all
data in the database.

**`migrate:fresh` ignores `down()`.** It drops the tables directly. So a broken `down()` stays
hidden until the day you actually need `rollback`. Test the round trip occasionally:

```bash
php artisan migrate
php artisan migrate:rollback
php artisan migrate
```

**Some changes cannot truly be rolled back.** If `up()` drops a column, `down()` can re-create the
column but the data inside it is gone forever. For a genuinely irreversible migration, say so
loudly rather than pretending:

```php
public function down(): void
{
    throw new \RuntimeException('This migration cannot be reversed.');
}
```

**Foreign keys make order matter.** If `up()` creates `notes` and then `comments` (which
references `notes`), `down()` must drop `comments` **first** — otherwise the database refuses to
drop a table that is still being pointed at.

## Quick Reference

Schema-level operations, for the body of `up()` / `down()`:

```php
Schema::create('notes', function (Blueprint $table) { ... });   // new table
Schema::table('notes', function (Blueprint $table) { ... });    // modify existing table
Schema::dropIfExists('notes');                                  // remove
Schema::rename('notes', 'articles');
```

Common column types and modifiers:

```php
$table->id();                              // auto-incrementing primary key
$table->string('title', 100);              // VARCHAR(100), default length 255
$table->text('body');                      // TEXT
$table->integer('views')->default(0);
$table->boolean('published')->default(false);
$table->decimal('price', 8, 2);            // 8 total digits, 2 after the point
$table->json('meta')->nullable();
$table->timestamp('published_at')->nullable();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->timestamps();                      // created_at + updated_at
$table->softDeletes();                     // deleted_at

$table->string('email')->unique();
$table->index(['title', 'created_at']);
```

## Naming Convention

`make:migration` reads the name you give it and pre-fills the right skeleton, so it is worth
following the convention:

```bash
php artisan make:migration create_notes_table        # → Schema::create('notes', ...)
php artisan make:migration add_slug_to_notes_table   # → Schema::table('notes', ...)
```

Table names are **plural and snake_case** (`notes`, `blog_posts`), which is what Eloquent expects
by default — a `Note` model looks for a `notes` table without being told to.
