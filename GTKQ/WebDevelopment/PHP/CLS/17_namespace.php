<?php
// Namespaces: naming, not loading.
// The supporting classes live in 17_namespace/App/... - open them alongside this.
//
// Three separate jobs, three separate keywords:
//   require   - loads and runs a file          (like C's #include)
//   namespace - declares a name prefix          (loads nothing)
//   use       - shortens a name in this file    (loads nothing)
// Python's import does all three at once. PHP splits them up.

// `use` statements must sit at the top of the file, right after any namespace
// declaration. They are compile-time aliases, so their position is fixed even
// though the classes they name are not loaded until further down.
use App\Models\User;                    // now "User" means App\Models\User
use App\Auth\User as AuthUser;          // ...and the other one needs a new name
use App\Models\Product;
use function App\Helpers\slugify;       // functions and constants can be imported
use const App\Helpers\VERSION;

echo "\n== 1. The problem namespaces solve ==\n";
echo "  Two different classes, both sensibly called 'User'.\n";
echo "  Their full names keep them apart:\n";
echo "    App\\Models\\User  - a row in the database\n";
echo "    App\\Auth\\User    - the visitor holding a session\n";
echo "  A namespace is only a prefix. It is not a file, module or package.\n";

echo "\n== 2. require loads the file. Nothing else does. ==\n";
require __DIR__ . '/17_namespace/App/Models/User.php';
require __DIR__ . '/17_namespace/App/Auth/User.php';
require __DIR__ . '/17_namespace/App/Helpers/text.php';
echo "  Both User classes are now in memory, with no collision.\n";

// A fully qualified name works anywhere, with or without `use`:
$record = new \App\Models\User("Aung");
echo "  " . $record->describe() . "\n";

echo "\n== 3. use is an alias, NOT an import ==\n";
// Because of the `use` line at the top, the short name now works:
$short = new User("Su");
echo "  " . $short->describe() . "\n";
echo "  Deleting the require above would break this, even with `use` present.\n";
echo "  'Class not found' despite a use statement means the file never loaded.\n";

echo "\n== 4. Aliasing away a collision ==\n";
$visitor = new AuthUser("abc123");
echo "  " . $visitor->describe() . "\n";
echo "  `use X as Y` renames it locally. Other files are unaffected.\n";

echo "\n== 5. An autoloader turns a name into a file ==\n";
// Registered once; PHP calls it whenever it meets a class it does not know.
spl_autoload_register(function (string $class): void {
    // App\Models\Product  ->  17_namespace/App/Models/Product.php
    $path = __DIR__ . '/17_namespace/' . str_replace('\\', '/', $class) . '.php';
    echo "    (autoloader looking for $class)\n";
    if (is_file($path)) {
        require $path;
    }
});

$widget = new Product("Widget", 9.99);  // never required by hand
echo "  " . $widget->label() . "\n";
echo "  Mapping namespace to folder is a CONVENTION (PSR-4), not a PHP rule.\n";
echo "  Composer writes this function for you - hence vendor/autoload.php.\n";

echo "\n== 6. Functions and constants fall back to global ==\n";
echo "  slugify: " . slugify("Hello World from PHP!") . "\n";
echo "  version: " . VERSION . "\n";
echo "  inside text.php, __NAMESPACE__ is: " . App\Helpers\whereAmI() . "\n";
echo "  slugify() calls strtolower() unqualified and it still works:\n";
echo "  PHP looks in App\\Helpers first, finds nothing, then tries global.\n";
echo "  Classes get NO such fallback - that is the asymmetry to remember.\n";

echo "\n== 7. The leading backslash ==\n";
// This file declares no namespace, so it IS the global namespace and the
// backslash changes nothing here:
$e1 = new RuntimeException("no backslash");
$e2 = new \RuntimeException("with backslash");
echo "  both are " . get_class($e1) . " and " . get_class($e2) . "\n";
echo "  Same reason 05_match.php can write UnhandledMatchError either way.\n";

// Inside a namespaced file it is a different story - see makeError():
$e3 = App\Helpers\makeError();
echo "  from a namespaced file: " . get_class($e3) . " - " . $e3->getMessage() . "\n";
echo "  There, \\RuntimeException was mandatory.\n";

echo "\n== 8. Asking a name about itself ==\n";
echo "  User::class      = " . User::class . "\n";        // resolved at compile time
echo "  AuthUser::class  = " . AuthUser::class . "\n";
echo "  get_class(\$short) = " . get_class($short) . "\n";
echo "  ::class expands the alias, so it always gives the FULL name.\n";
echo "  Prefer it over a hand-typed string - typos become compile errors.\n";

echo "\n";
