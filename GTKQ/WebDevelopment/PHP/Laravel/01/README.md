# Laravel — Creating Your First App

## Prerequisites

Laravel needs PHP and Composer (the PHP package manager).

```bash
# In Mac
brew install php
brew install composer

php -v         # Laravel 13 requires PHP 8.3 or newer ("php": "^8.3" in composer.json)
composer -V
```

Laravel also needs a few PHP extensions (`mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`,
`ctype`, `json`, `curl`). The `brew install php` build already includes all of them.

## Create the App

Navigate to the folder where this file resides.

There are two ways to create the app, and they achieve the same thing.
**Use one or the other — not both, and not one after the other.**

Note that they differ in who creates the project folder, so the `cd` step is not the same.

### Option A — Composer (recommended)

Nothing to install first. You make the folder, then fill it:

```bash
mkdir testprj && cd testprj
composer create-project laravel/laravel .
```

The `.` at the end means "create the project in the **current** folder" instead of creating a new
subfolder — which is why the folder has to exist and be the working directory first. This route
produces the plain skeleton with no questions asked.

### Option B — the Laravel installer

A convenience wrapper. It needs a one-time tool install, so here the two commands **do** run in
sequence — install the tool, then use it:

```bash
composer global require laravel/installer   # once, ever — installs the `laravel` command
laravel new testprj                         # then this creates the app
```

Run this from the folder where this README is, **without** creating `testprj` yourself —
`laravel new testprj` makes that folder as part of its job. (Pre-creating it and running the
command inside would give you `testprj/testprj`.)

Unlike Option A, this one asks a few questions:

```
Would you like to install a starter kit? › none / react / vue / livewire
Which testing framework do you prefer? › Pest / PHPUnit
Which database will your application use? › SQLite / MySQL / MariaDB / PostgreSQL
Would you like to run the default database migrations? › yes / no
```

For a basic start, accept the defaults — no starter kit and SQLite.

> **`composer global require` is not a project command.** It installs a command-line tool into
> `~/.composer` for your whole user account, and the folder you run it from is irrelevant —
> running it inside a project adds nothing to that project's `composer.json` or `vendor/`.
> See [Notes/Composer.md](../Notes/Composer.md) for the full distinction.

> **If `laravel new` says "command not found"** after the global install, the tool is installed
> but its folder is not on your `PATH`. Find it with
> `composer global config bin-dir --absolute` (typically `~/.composer/vendor/bin`) and add it in
> `~/.zshrc`:
>
> ```bash
> export PATH="$HOME/.composer/vendor/bin:$PATH"
> ```

### Either way

Both end up with the same project. SQLite is the default database and needs no database server —
it is just a file at `database/database.sqlite`.

## Run the App

```bash
php artisan serve
```

Then open your browser at `http://localhost:8000`.

You get Laravel's default welcome page. Everything on it — **Documentation**, **Laracasts**,
**Deploy now**, **View changelog** — is a plain external hyperlink in
`resources/views/welcome.blade.php`. "Deploy now" points at
[Laravel Cloud](https://cloud.laravel.com), Laravel's paid hosting platform; it is an advert, not
a function, and clicking it does nothing to your project. That whole file is a placeholder and is
safe to replace or delete.

`artisan` is Laravel's command-line tool — the same file is used for generating code,
running migrations, clearing caches, and so on.

## What Gets Created

```
testprj/
├── app/
│   ├── Http/Controllers/     # Controllers — the code behind each route
│   ├── Models/               # Eloquent models (one class per database table)
│   └── Providers/            # Application bootstrapping
├── bootstrap/                # Framework startup files (rarely touched)
├── config/                   # Configuration files (app, database, mail, ...)
├── database/
│   ├── migrations/           # Versioned database schema changes
│   ├── seeders/              # Sample/starter data
│   └── database.sqlite       # The SQLite database file (if SQLite was chosen)
├── public/
│   └── index.php             # The single entry point — every request comes through here
├── resources/
│   ├── views/                # Blade templates (.blade.php)
│   ├── css/  js/             # Frontend assets
├── routes/
│   ├── web.php               # Browser routes
│   └── console.php           # Custom artisan commands
├── storage/                  # Logs, caches, compiled views, uploaded files
├── tests/                    # Tests
├── vendor/                   # Installed dependencies (auto-generated)
├── .env                      # Environment settings — DB, app key, debug flag
├── artisan                   # The command-line tool
└── composer.json             # Project dependencies and scripts
```

## Key Files to Know

| File | Purpose |
|------|---------|
| `routes/web.php` | Maps URLs to code — the place to start reading any Laravel app |
| `app/Http/Controllers/` | Where request-handling classes live |
| `resources/views/welcome.blade.php` | The home page you see at `/` — edit this to change it |
| `.env` | Database credentials, app key, `APP_DEBUG` — never committed to git |
| `config/app.php` | App settings; reads mostly from `.env` |
| `composer.json` | Lists dependencies; `composer install` recreates `vendor/` |

## A Minimal Walkthrough

### 1. A route that returns text

In `routes/web.php`:

```php
Route::get('/hello', function () {
    return 'Hello from Laravel';
});
```

Visit `http://localhost:8000/hello`.

### 2. A route that returns a view

Create `resources/views/greet.blade.php`:

```blade
<h1>Hello, {{ $name }}</h1>
```

And in `routes/web.php`:

```php
Route::get('/greet/{name}', function (string $name) {
    return view('greet', ['name' => $name]);
});
```

Visit `http://localhost:8000/greet/Zaw`.

`{{ }}` is Blade's echo syntax — it escapes HTML automatically, unlike a raw `<?= ?>`.

### 3. The same thing through a controller

```bash
php artisan make:controller GreetController
```

In `app/Http/Controllers/GreetController.php`:

```php
public function show(string $name)
{
    return view('greet', ['name' => $name]);
}
```

And in `routes/web.php`:

```php
use App\Http\Controllers\GreetController;

Route::get('/greet/{name}', [GreetController::class, 'show']);
```

Controllers are the normal way once a route needs more than a line or two.

### 4. A table and a model

```bash
php artisan make:model Note --migration
```

In the new file under `database/migrations/`:

```php
Schema::create('notes', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('body');
    $table->timestamps();
});
```

Then apply it:

```bash
php artisan migrate
```

### 5. Reading and writing the table

Before the model can accept an array of values, it has to say which columns are safe to fill.
In `app/Models/Note.php`:

```php
class Note extends Model
{
    protected $fillable = ['title', 'body'];
}
```

Without that line, `Note::create([...])` throws `MassAssignmentException`. Laravel refuses by
default so that a stray form field cannot quietly write to a column you never meant to expose —
`is_admin` being the classic example.

Now one route per operation, in `routes/web.php`:

```php
use App\Models\Note;
use Illuminate\Http\Request;

// INSERT — add one row, values from the query string
// e.g. /notes/add?title=Shopping&body=Buy%20milk
Route::get('/notes/add', function (Request $request) {
    return Note::create([
        'title' => $request->query('title', 'Untitled'),
        'body'  => $request->query('body', ''),
    ]);
});

// SELECT ALL — list every row
Route::get('/notes', function () {
    return Note::all();
});

// DELETE — remove one row by id
Route::get('/notes/delete/{id}', function (int $id) {
    Note::findOrFail($id)->delete();

    return "Deleted note {$id}";
});
```

Visit them in order:

| URL | What happens |
|-----|--------------|
| `http://localhost:8000/notes/add?title=Shopping&body=Buy milk` | Inserts that row, returns it as JSON with its new `id` |
| `http://localhost:8000/notes/add` | Inserts a row using the defaults, `Untitled` and empty |
| `http://localhost:8000/notes` | Returns every row as a JSON array |
| `http://localhost:8000/notes/delete/1` | Deletes note 1, returns a plain sentence |

Returning a model or a collection from a route gives JSON automatically — no encoding step.

`create()` inserts and returns the saved model in one call. `all()` fetches every row as a
collection. `findOrFail($id)` looks up one row and raises a 404 if it does not exist, which is
why deleting an already-deleted id gives a clean "not found" page instead of a crash.

### Where the values come from

Type-hinting `Request $request` in the closure asks Laravel for the current request object; it
is handed over automatically, with nothing to construct or pass. From it:

```php
$request->query('title')              // from the ?query=string only
$request->query('title', 'Untitled')  // ... with a fallback when absent
$request->input('title')              // query string OR form body — use this for POST
$request->all()                       // everything, as an array
```

The second argument is the default, which is what keeps a bare `/notes/add` from inserting
`null` into a `NOT NULL` column. Type spaces straight into the address bar — the browser encodes
them to `%20` for you.

A route parameter would be the other option, `/notes/add/{title}/{body}`, but it suits this
badly: every value becomes mandatory, the order has to be remembered, and any text containing a
`/` breaks the match. Query strings are the normal choice for optional, unordered values.

**Real applications validate before inserting** rather than trusting whatever arrived:

```php
Route::post('/notes', function (Request $request) {
    $data = $request->validate([
        'title' => 'required|string|max:255',
        'body'  => 'required|string',
    ]);

    return Note::create($data);
});
```

`validate()` returns only the fields that passed the rules, so the array handed to `create()` is
already clean — and that pairs with `$fillable` as the second line of defence. On failure it
throws, and Laravel turns that into a redirect back to the form (or a 422 JSON response for an
API), so the closure body never runs with bad data.

> **These are all `Route::get` for convenience.** A browser address bar can only issue GET
> requests, so that is what makes them clickable while learning. Real applications use
> `Route::post` for inserts and `Route::delete` for deletions — a URL that changes data on a
> plain visit is something a search-engine crawler can trigger by accident.

## Useful artisan Commands

| Command | What it does |
|---------|--------------|
| `php artisan serve` | Start the development server |
| `php artisan route:list` | Show every registered route |
| `php artisan make:controller NameController` | Generate a controller |
| `php artisan make:model Name --migration` | Generate a model plus its migration |
| `php artisan migrate` | Apply pending migrations |
| `php artisan migrate:fresh` | Drop all tables and re-run migrations |
| `php artisan tinker` | Interactive REPL with the app booted |
| `php artisan config:clear` | Clear cached configuration |

## Key Information

Laravel is a framework, not a library — the generated project is a full application structure,
so it is far from the thin, single-file scripts in the [CLS](../../CLS/README.md) folder.
Every request goes through `public/index.php`, which boots the framework and hands the request
to `routes/web.php`. Almost everything else — controllers, models, views — is reached from there.

`vendor/` and `.env` are generated locally and are normally excluded from git; `composer install`
plus a copy of `.env.example` recreates them on another machine.