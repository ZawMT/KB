<?php
// Recursion: a function that calls itself.
// Every recursive function needs two things:
//   1. a BASE CASE   - when to stop
//   2. a SMALLER STEP - a call to itself on a smaller problem

echo "\n== 1. The shape of a recursive function ==\n";

function countdown(int $n): void
{
    if ($n <= 0) {                  // BASE CASE - stop here
        echo "  liftoff!\n";
        return;
    }
    echo "  $n\n";
    countdown($n - 1);              // SMALLER STEP - same job, smaller number
}

countdown(5);

echo "\n== 2. Returning a value: factorial ==\n";
// 5! = 5 * 4 * 3 * 2 * 1 = 120

function factorial(int $n): int
{
    if ($n <= 1) {
        return 1;                   // base case
    }
    return $n * factorial($n - 1);  // this call waits for the next one to finish
}

foreach ([0, 1, 5, 10] as $n) {
    echo "  $n! = " . factorial($n) . "\n";
}

echo "\n  How factorial(4) unfolds:\n";
echo "    factorial(4) = 4 * factorial(3)\n";
echo "    factorial(3) = 3 * factorial(2)\n";
echo "    factorial(2) = 2 * factorial(1)\n";
echo "    factorial(1) = 1              <- base case reached\n";
echo "  then it unwinds back up: 1, 2, 6, 24\n";

echo "\n== 3. Recursion over data, not numbers ==\n";
// A nested array has no fixed depth, so a plain foreach cannot reach the bottom.

$nested = [1, [2, 3, [4, 5]], 6, [[7]]];

function deepSum(array $items): int
{
    $total = 0;
    foreach ($items as $item) {
        if (is_array($item)) {
            $total += deepSum($item);   // an array inside: recurse into it
        } else {
            $total += $item;            // a plain value: just add it
        }
    }
    return $total;
}

echo "  " . json_encode($nested) . "\n";
echo "  deep sum = " . deepSum($nested) . "\n";   // 28
echo "  A single foreach would only see 1, 6 and three arrays.\n";

echo "\n== 4. Walking a tree ==\n";
// The classic real use: menus, folders, comment threads, categories.

$menu = [
    ["name" => "Home"],
    ["name" => "Products", "children" => [
        ["name" => "Phones", "children" => [
            ["name" => "Android"],
            ["name" => "iPhone"],
        ]],
        ["name" => "Laptops"],
    ]],
    ["name" => "Contact"],
];

function printMenu(array $items, int $depth = 0): void
{
    foreach ($items as $item) {
        echo "  " . str_repeat("    ", $depth) . "- {$item['name']}\n";
        if (isset($item["children"])) {
            printMenu($item["children"], $depth + 1);   // one level deeper
        }
    }
}

printMenu($menu);
echo "  The \$depth argument is only for the indentation, not for the logic.\n";

echo "\n== 5. Two calls per step: fibonacci ==\n";
// 0, 1, 1, 2, 3, 5, 8, 13 ... each number is the sum of the previous two.

function fib(int $n): int
{
    if ($n < 2) {
        return $n;                  // base cases: fib(0)=0, fib(1)=1
    }
    return fib($n - 1) + fib($n - 2);
}

echo "  ";
for ($i = 0; $i < 10; $i++) {
    echo fib($i) . " ";
}
echo "\n";

// This is elegant but wasteful - fib(30) recomputes fib(5) hundreds of times.
// Remembering answers ("memoising") fixes it:
function fibFast(int $n, array &$memo = []): int
{
    if ($n < 2) {
        return $n;
    }
    if (isset($memo[$n])) {
        return $memo[$n];           // already worked this one out
    }
    return $memo[$n] = fibFast($n - 1, $memo) + fibFast($n - 2, $memo);
}

echo "  fibFast(50) = " . fibFast(50) . "\n";
echo "  Plain fib(50) would take longer than you are willing to wait.\n";

echo "\n== 6. Forgetting the base case ==\n";
// Each call keeps its own variables in memory until it returns. PHP does not
// optimise this away, so a runaway recursion exhausts memory and crashes.
echo "  function boom(\$n) { return boom(\$n - 1); }   // never stops\n";
echo "  Always ask: what stops it, and does every call get closer to that?\n";

// Recursion is a choice, not a requirement - a loop does factorial fine:
function factorialLoop(int $n): int
{
    $result = 1;
    for ($i = 2; $i <= $n; $i++) {
        $result *= $i;
    }
    return $result;
}
echo "  loop version:      factorialLoop(5) = " . factorialLoop(5) . "\n";
echo "  recursive version: factorial(5)     = " . factorial(5) . "\n";
echo "  Use recursion when the DATA is nested and you cannot know how deep.\n";

echo "\n";
