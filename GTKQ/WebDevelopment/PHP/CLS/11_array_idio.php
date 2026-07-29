<?php
// Array oddities. Run this and read down the output.
// Companion to 10_array.php - everything deliberately surprising lives here.
// Some sections emit Deprecated notices ON PURPOSE; they are labelled.

function dump(string $label, array $arr): void
{
    printf("  %-24s %s\n", $label, json_encode($arr));
}

echo "\n== 1. == ignores order, === does not ==\n";
$a = ["x" => 1, "y" => 2];
$b = ["y" => 2, "x" => 1];          // same pairs, different order
var_dump($a == $b);                 // true  - same keys and values
var_dump($a === $b);                // false - order must match too
// And == compares values loosely, so types slip through:
var_dump([1, 2] == ["1", "2"]);     // true
var_dump([1, 2] === ["1", "2"]);    // false
echo "  Use === unless you have a reason not to.\n";

echo "\n== 2. + is not array_merge ==\n";
$left  = ["a", "b"];                // 0, 1
$right = ["c", "d", "e"];           // 0, 1, 2
dump("left + right", $left + $right);          // a, b, e  - LEFT wins per key
dump("array_merge", array_merge($left, $right)); // a,b,c,d,e - all kept
echo "  + fills in missing keys only. array_merge renumbers int keys.\n";

// With string keys the winner flips:
$defaults = ["host" => "localhost", "port" => 80];
$config   = ["port" => 8080];
dump("defaults + config", $defaults + $config);            // port 80
dump("array_merge", array_merge($defaults, $config));      // port 8080
echo "  For config defaults you usually want \$config + \$defaults.\n";

echo "\n== 3. Numeric string keys are int keys ==\n";
// So array_merge treats them as positions and renumbers them.
$x = ["5" => "a"];
$y = ["5" => "b"];
dump("array_merge", array_merge($x, $y));   // 0=>a, 1=>b  - key 5 is gone!
dump("x + y", $x + $y);                     // 5=>a        - left wins, key kept
echo "  Bite: merging two ID-keyed arrays silently destroys the IDs.\n";

echo "\n== 4. Spread renumbers too ==\n";
dump("[...left, ...right]", [...$left, ...$right]);        // a,b,c,d,e
dump("[...defaults, ...config]", [...$defaults, ...$config]); // port 8080
echo "  Spread behaves like array_merge, not like +.\n";

echo "\n== 5. array_filter keeps the ORIGINAL keys ==\n";
$nums = [1, 2, 3, 4, 5, 6];
$even = array_filter($nums, fn($n) => $n % 2 === 0);
print_r($even);                             // 1=>2, 3=>4, 5=>6
echo "  json_encode(\$even) = " . json_encode($even) . "\n";       // an OBJECT
echo "  after array_values  = " . json_encode(array_values($even)) . "\n"; // an array
echo "  This is how a JSON API accidentally returns {} instead of [].\n";

echo "\n== 6. A gap turns a list into a JSON object ==\n";
$list = ["a", "b", "c"];
echo "  full list:  " . json_encode($list) . "\n";       // ["a","b","c"]
unset($list[1]);
echo "  after unset: " . json_encode($list) . "\n";      // {"0":"a","2":"c"}
var_dump(array_is_list($list));                          // false (PHP 8.1+)
echo "  fixed:      " . json_encode(array_values($list)) . "\n";
echo "  Rule: array_values() before json_encode() if it must be a JSON array.\n";

echo "\n== 7. sort() returns bool, not the sorted array ==\n";
$s = [3, 1, 2];
$result = sort($s);                 // sorts IN PLACE
var_dump($result);                  // bool(true) - NOT the array
dump("\$s after sort()", $s);
echo "  \$s = sort(\$s) would leave \$s as true. Never write that.\n";

// sort() also throws the keys away:
$scores = ["Su" => 25, "Aung" => 30];
sort($scores);
dump("sort() on named keys", $scores);   // 0=>25, 1=>30 - names lost
echo "  Use asort()/ksort() when keys matter.\n";

echo "\n== 8. array_search can return a falsy key ==\n";
$fruits = ["apple", "banana"];
$i = array_search("apple", $fruits, true);   // 0 - a valid key
var_dump($i);
if (!$i) {
    echo "  WRONG: plain if() says 'not found' because 0 is falsy\n";
}
if ($i !== false) {
    echo "  RIGHT: !== false says 'found at index $i'\n";
}

echo "\n== 9. in_array without strict is loose ==\n";
var_dump(in_array("1", [1, 2, 3]));         // true  - "1" == 1
var_dump(in_array("1", [1, 2, 3], true));   // false - different types
var_dump(in_array(null, [""]));             // true  - null == ""
var_dump(in_array("abc", [0]));             // false in PHP 8+ (was TRUE in PHP 7)
echo "  PHP 8 fixed string-vs-number, but pass strict=true anyway.\n";

echo "\n== 10. array_unique compares as STRINGS by default ==\n";
dump("mixed types", array_unique([1, "1", 1.0, true]));   // just the first one
echo "  Default is SORT_STRING, so 1, \"1\", 1.0 and true are all duplicates.\n";
// Where the flag actually changes the answer:
dump("\"1\",\"01\" as strings", array_unique(["1", "01", 1]));                  // "1", "01"
dump("\"1\",\"01\" as numbers", array_unique(["1", "01", 1], SORT_REGULAR));    // "1"
echo "  \"1\" and \"01\" differ as text but are equal as numbers.\n";

echo "\n== 11. Deprecated key coercions (notices below are expected) ==\n";
// Both still work in PHP 8.5, but PHP is phasing them out.
$float = [];
$float[5.9] = "truncated to 5";     // Deprecated since PHP 8.1
dump("float key 5.9", $float);

$nul = [];
$nul[null] = "became empty string"; // Deprecated - write "" yourself
dump("null key", $nul);
echo "  Write \$a[5] and \$a[\"\"] explicitly instead.\n";

echo "\n== 12. Appending after a negative key ==\n";
$neg = [-5 => "x"];
$neg[] = "y";
dump("after append", $neg);
echo "  -4 in PHP 8.3+, but 0 in older versions. Do not rely on either.\n";

echo "\n== 13. A reference inside an array survives copying ==\n";
// Arrays copy by value - EXCEPT for slots that hold a reference.
$src = [1, 2, 3];
$ref = &$src[1];                    // make slot 1 a reference
$copy = $src;                       // "copy"
$copy[1] = 99;                      // writes through the shared reference
dump("\$src (should be 1,2,3)", $src);   // 1, 99, 3  - leaked!
dump("\$copy", $copy);
unset($ref);
echo "  unset() the reference before copying, or avoid &\$arr[i] entirely.\n";

echo "\n== 14. Destructuring a missing key ==\n";
// Warns and gives null rather than throwing.
$row = ["age" => 30];
["name" => $name, "age" => $age] = $row;   // Warning: Undefined array key "name"
var_dump($name, $age);
// Safe version:
["name" => $name2, "age" => $age2] = $row + ["name" => "unknown"];
echo "  with a default: $name2, $age2\n";

echo "\n";
