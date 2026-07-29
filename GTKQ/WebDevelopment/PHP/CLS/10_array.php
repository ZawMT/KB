<?php
// Arrays: PHP has only ONE array type - an ordered dictionary (key => value).
// A "list" is just the case where the keys happen to be 0, 1, 2, ...

echo "\n== 1. Two ways to write the same thing ==\n";
$list  = ["apple", "banana", "cherry"];
$dict  = [0 => "apple", 1 => "banana", 2 => "cherry"];
// Identical: === compares keys, values, order and types.
var_dump($list === $dict);          // bool(true)

$ages = ["Aung" => 30, "Su" => 25]; // string keys - same array type
echo "  Aung is {$ages['Aung']}\n";

echo "\n== 2. Keys: only int and string ==\n";
// Anything else is coerced:
//   "5"   -> int 5    (a numeric string becomes an int)
//   true  -> int 1    (bools become 0 / 1)
$mixed = ["5" => "a", 5 => "b", true => "c"];
echo "  printing the array \$mixed:\n";
var_dump($mixed);
// 2 entries, not 3: "5" and 5 are the same key, so "b" overwrote "a".
echo "  and just its keys:\n";
var_dump(array_keys($mixed));       // int(5), int(1)
// Careful reading this: array_keys() returns a plain list, so the [0] [1]
// on the left are positions in THAT list - the keys are the values on the right.

// Two more coercions exist but are deprecated, so do not rely on them:
//   5.9  -> int 5     truncated (deprecated since PHP 8.1 - it loses precision)
//   null -> ""        the empty string (deprecated - write "" yourself)
// Both still run today, but each emits a Deprecated notice. See 11_array_idio.php.

echo "\n== 3. The auto-key counter ==\n";
// Next auto key = (largest int key ever used) + 1. It never rewinds.
$a = ["x", "y", "z"];               // 0, 1, 2
unset($a[2]);                       // now 0, 1
$a[] = "w";                         // key 3 - not 2
print_r(array_keys($a));

$b[10]     = "a";
$b[]       = "b";                   // 11
$b["name"] = "c";                   // string key - counter untouched
$b[]       = "d";                   // 12
print_r(array_keys($b));

echo "\n== 4. Reading safely ==\n";
$user = ["name" => "Su", "city" => null];
echo "  name:  " . ($user["name"] ?? "unknown") . "\n";
echo "  email: " . ($user["email"] ?? "unknown") . "\n";  // missing -> no warning
// ?? treats null and missing the same. To tell them apart:
var_dump(isset($user["city"]));            // false - value is null
var_dump(array_key_exists("city", $user)); // true  - key is there

echo "\n== 5. Adding and removing ==\n";
$stack = ["a", "b"];
$stack[]  = "c";                    // append (fastest)
array_push($stack, "d", "e");       // append several
array_unshift($stack, "z");         // prepend - reindexes everything, slower
$last  = array_pop($stack);         // "e"
$first = array_shift($stack);       // "z" - also reindexes
echo "  took $first and $last, left: " . implode(",", $stack) . "\n";

unset($stack[1]);                   // removes the key, leaves a gap
print_r(array_keys($stack));
print_r(array_keys(array_values($stack)));  // array_values renumbers 0..n-1

echo "\n== 6. Size and emptiness ==\n";
$rows = [[1, 2], [3, 4], [5, 6]];
echo "  count: " . count($rows) . "\n";
echo "  deep:  " . count($rows, COUNT_RECURSIVE) . "\n";   // 9 = 3 rows + 6 cells
var_dump(empty([]), $rows === []);

echo "\n== 7. Searching ==\n";
$fruits = ["apple", "banana", "cherry"];
var_dump(in_array("banana", $fruits, true));    // true - always pass strict=true
var_dump(array_search("cherry", $fruits, true));// 2 (the key)
// array_search returns false when not found, and 0 is a valid key,
// so test with === false, never with a plain if.
if (array_search("apple", $fruits, true) !== false) {
    echo "  apple is present\n";
}

echo "\n== 8. Looping ==\n";
foreach ($ages as $name => $age) {          // key => value
    echo "  $name is $age\n";
}
foreach ($fruits as $fruit) {               // value only
    echo "  $fruit\n";
}
foreach ($fruits as $i => $fruit) {         // key when you need position
    echo "  [$i] $fruit\n";
}

echo "\n== 9. Nested arrays ==\n";
$grid = [[1, 2, 3], [4, 5, 6]];
echo "  middle cell: {$grid[0][1]}\n";
foreach ($grid as $r => $row) {
    foreach ($row as $c => $cell) {
        echo "  ($r,$c) = $cell\n";
    }
}

$people = [
    ["name" => "Aung", "age" => 30],
    ["name" => "Su",   "age" => 25],
];
foreach ($people as $p) {
    echo "  {$p['name']}, {$p['age']}\n";    // note the quotes inside {}
}

echo "\n== 10. Unpacking into variables ==\n";
[$x, $y, $z] = [1, 2, 3];                    // positional
echo "  x=$x y=$y z=$z\n";
["name" => $n, "age" => $ag] = $people[0];   // by key
echo "  $n is $ag\n";
foreach ($people as ["name" => $pn]) {       // works in foreach too
    echo "  hello $pn\n";
}
[, $second] = [1, 2];                        // skip a position
echo "  second=$second\n";

echo "\n== 11. Combining ==\n";
$a1 = ["a", "b"];
$a2 = ["c", "d"];
print_r([...$a1, ...$a2]);                   // spread - renumbers list keys
print_r(array_merge($a1, $a2));              // same result for lists

// For string keys, later wins in both:
$defaults = ["host" => "localhost", "port" => 80];
$config   = ["port" => 8080];
print_r(array_merge($defaults, $config));    // port 8080
print_r([...$defaults, ...$config]);         // same

echo "\n== 12. Transforming ==\n";
$nums = [1, 2, 3, 4, 5];
print_r(array_map(fn($n) => $n * $n, $nums));               // squares
print_r(array_filter($nums, fn($n) => $n % 2 === 0));       // keeps ORIGINAL keys
print_r(array_values(array_filter($nums, fn($n) => $n % 2 === 0)));
echo "  sum: " . array_sum($nums) . "\n";
echo "  reduce: " . array_reduce($nums, fn($carry, $n) => $carry + $n, 0) . "\n";

echo "\n== 13. Sorting ==\n";
// These sort IN PLACE and return bool - do not write $x = sort($x).
$s = [3, 1, 2];
sort($s);                                    // by value, keys discarded
print_r($s);

$scores = ["Su" => 25, "Aung" => 30, "Min" => 28];
asort($scores);                              // by value, keys kept
print_r($scores);
ksort($scores);                              // by key
print_r($scores);
usort($people, fn($p, $q) => $p["age"] <=> $q["age"]);  // custom
echo "  youngest: {$people[0]['name']}\n";

echo "\n== 14. Keys, values, and strings ==\n";
print_r(array_keys($scores));
print_r(array_values($scores));
print_r(array_flip($scores));                // swap key and value
print_r(array_unique([1, 2, 2, 3]));         // keeps first occurrence + its key
echo "  " . implode(", ", $fruits) . "\n";   // array -> string
print_r(explode(",", "a,b,c"));              // string -> array

echo "\n== 15. Copy by value ==\n";
// Assigning an array COPIES it (objects are the exception - those are handles).
$orig = [1, 2, 3];
$copy = $orig;
$copy[] = 4;
echo "  orig " . count($orig) . ", copy " . count($copy) . "\n";  // 3, 4
// Use &$ref only when you deliberately want to write back.
$byref = &$orig;
$byref[] = 4;
echo "  orig is now " . count($orig) . "\n";                      // 4

echo "\n== 16. Printing while debugging ==\n";
print_r($ages);                              // readable
var_dump($ages);                             // adds types and lengths
echo json_encode($ages) . "\n";              // compact, one line
