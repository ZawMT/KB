<?php
// include and require: pasting one file into another at runtime.
// Supporting files are in 18_include/.

echo "\n== 1. The four keywords ==\n";
echo "  include       missing file -> Warning, script continues\n";
echo "  require       missing file -> Fatal error, script stops\n";
echo "  include_once  same as include, but skips a file already loaded\n";
echo "  require_once  same as require,  but skips a file already loaded\n";
echo "  Rule of thumb: require_once for code, include for optional extras.\n";

echo "\n== 2. An included file shares your variables ==\n";
$name = "Aung";
include __DIR__ . '/18_include/greeting.php';   // sees $name
echo "  and it set \$greeted = " . var_export($greeted, true) . "\n";
echo "  The file is pasted in where you wrote include - same scope, both ways.\n";

echo "\n== 3. A file can return a value ==\n";
$config = require __DIR__ . '/18_include/config.php';
print_r($config);
echo "  Nothing leaked into this script - the settings came back as a value.\n";
echo "  This is how framework config files work.\n";

echo "\n== 4. _once prevents redeclaration ==\n";
require_once __DIR__ . '/18_include/helpers.php';
require_once __DIR__ . '/18_include/helpers.php';   // silently skipped
echo "  " . shout("loaded twice, declared once") . "\n";
// require __DIR__ . '/18_include/helpers.php';     // Fatal: cannot redeclare shout()
echo "  Plain require here would be a fatal error.\n";

echo "\n== 5. Missing files: the difference ==\n";
// The Warning below is expected.
$ok = include __DIR__ . '/18_include/nope.php';
var_dump($ok);                                  // false
echo "  include returned false and we carried on.\n";
// require __DIR__ . '/18_include/nope.php';    // would stop the script dead
echo "  require would have ended the script right there.\n";

echo "\n== 6. Always anchor paths with __DIR__ ==\n";
echo "  __DIR__ = " . __DIR__ . "\n";
echo "  getcwd() = " . getcwd() . "\n";
// A bare '18_include/config.php' is resolved against the CURRENT WORKING
// DIRECTORY, not this file's folder. Run the script from elsewhere and it
// breaks. __DIR__ is always this file's own folder, so it never does.
echo "  Bare relative paths follow getcwd(), which the caller controls.\n";
echo "  __DIR__ . '/...' is the only form that works from any directory.\n";

echo "\n== 7. How this differs from `use` ==\n";
echo "  require  loads a file       (17_namespace.php section 2)\n";
echo "  use      renames a class    (17_namespace.php section 3)\n";
echo "  Neither one does the other's job.\n";

echo "\n";
