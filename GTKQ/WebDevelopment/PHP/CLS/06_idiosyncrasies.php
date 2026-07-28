<?php
// PHP oddities worth knowing. Run this file and read down the output.
// Every result below is real PHP 8 behaviour, not a typo.

function show(string $expr, $result, string $why): void
{
    printf("  %-26s => %-7s %s\n", $expr, var_export($result, true), $why);
}

echo "\n== 1. Loose comparison (==) between strings ==\n";
// Two NUMERIC strings are compared as numbers, never as text.
show('"1e2" == "100"', "1e2" == "100", "1e2 is scientific notation for 100");
show('"0.0" == "0"',   "0.0" == "0",   "0.0 and 0 are the same number");
show('"007" == "7"',   "007" == "7",   "leading zeros vanish in a number");
show('"10" == "1e1"',  "10" == "1e1",  "both numeric, both equal 10");
show('"abc" == "ABC"', "abc" == "ABC", "NOT numeric, so compared as text");

echo "\n== 2. Loose comparison against bool and null ==\n";
// Comparing to a bool converts the OTHER side to bool first.
show('true == "abc"',  true == "abc",  "any non-empty string is truthy");
show('true == "0"',    true == "0",    'the string "0" is the falsy exception');
show('true == -1',     true == -1,     "any non-zero number is truthy, even negative");
show('null == ""',     null == "",     "null and empty string are both empty");
show('null == 0',      null == 0,      "null converts to 0");
show('null == "0"',    null == "0",    'null vs string compares "" to "0"');

echo "\n== 3. The rule that CHANGED in PHP 8 ==\n";
// In PHP 5/7 both of these were true. The old advice is everywhere online.
show('0 == "abc"',     0 == "abc",     "PHP 8: int becomes a string, compared as text");
show('1 == "1abc"',    1 == "1abc",    'PHP 8: "1" vs "1abc" as text -> not equal');
echo "  (Both were TRUE before PHP 8 - beware of old tutorials.)\n";

echo "\n== 4. === removes all of the above ==\n";
show('"1e2" === "100"', "1e2" === "100", "same type AND same value required");
show('0 === "0"',       0 === "0",       "int is not string");
show('null === false',  null === false,  "null is not false");
echo "  Rule of thumb: use === unless you have a reason not to.\n";

echo "\n== 5. Floating point maths ==\n";
$sum = 0.1 + 0.2;
show('0.1 + 0.2 == 0.3', $sum == 0.3, "binary floats cannot store 0.1 exactly");
printf("  %-26s => %s\n", '0.1 + 0.2 printed raw', var_export($sum, true));
show('round($sum,10)==0.3', round($sum, 10) == 0.3, "round before comparing floats");

echo "\n== 6. Strings that look like numbers ==\n";
show('"10" > "9"',     "10" > "9",     "both numeric -> compared as numbers");
show('"10" > "9a"',    "10" > "9a",    'not numeric -> text, and "1" < "9"');
show('(int) "12abc"',  (int) "12abc",  "cast keeps the leading number, silently");
show('(int) "abc"',    (int) "abc",    "no leading number at all -> 0");

echo "\n== 7. String increment ==\n";
// $s++ on a letter still works, but PHP 8.3+ deprecates it in favour of
// str_increment(). Same result, no deprecation notice.
show('str_increment("a")',  str_increment("a"),  "letters increment like spreadsheet columns");
show('str_increment("z")',  str_increment("z"),  "wraps around and grows a new letter");
show('str_increment("a9")', str_increment("a9"), "the digit carries into the letter");
echo "  (Plain \$s++ does the same but is deprecated since PHP 8.3.)\n";

echo "\n== 8. Array keys ==\n";
$arr = ["1" => "first", 1 => "second"];
show('count(["1"=>..., 1=>...])', count($arr), 'the string key "1" becomes int 1');
show('$arr[1]', $arr[1], "so the second value overwrote the first");

$x = [0 => "a", 1 => "b"];
$y = [1 => "b", 0 => "a"];
show('$x == $y',  $x == $y,  "== ignores the order of keys");
show('$x === $y', $x === $y, "=== requires the same order too");

echo "\n== 9. empty() and isset() ==\n";
$zero = "0";
$null = null;
show('empty("0")',      empty($zero),  'the string "0" counts as empty!');
show('empty(0.0)',      empty(0.0),    "so does 0.0, and [] and null");
show('isset($null)',    isset($null),  "isset() is false for a variable set to null");
show('$undefined ?? 5', $undefined ?? 5, "?? gives a default with no warning");

echo "\n== 10. Integer overflow ==\n";
show('PHP_INT_MAX', PHP_INT_MAX, "largest int on this machine");
show('PHP_INT_MAX + 1', PHP_INT_MAX + 1, "silently becomes a float, losing precision");

echo "\n";
