<?php
// The same job done four ways: print a numbered, upper-cased list.
// Compare how much bookkeeping each loop needs.

$fruits = ["apple", "banana", "cherry"];

echo "\n== 1. foreach - the natural fit for arrays ==\n";
// PHP hands you the key and the value. No counter to set up or increment.
foreach ($fruits as $i => $fruit) {
    echo ($i + 1) . ". " . strtoupper($fruit) . "\n";
}

echo "\n== 2. for - you manage the counter ==\n";
// Init, condition and step all live on one line.
for ($i = 0; $i < count($fruits); $i++) {
    echo ($i + 1) . ". " . strtoupper($fruits[$i]) . "\n";
}

echo "\n== 3. while - the counter is spread out ==\n";
// Same three parts as 'for', just scattered: before, in, and inside the loop.
$i = 0;                          // init
while ($i < count($fruits)) {    // condition
    echo ($i + 1) . ". " . strtoupper($fruits[$i]) . "\n";
    $i++;                        // step - forget this and the loop never ends
}

echo "\n== 4. do...while - body first, test afterwards ==\n";
// The condition is checked at the BOTTOM, so the body always runs once.
// With a possibly-empty array that is wrong, hence the guard.
$i = 0;
if ($fruits !== []) {
    do {
        echo ($i + 1) . ". " . strtoupper($fruits[$i]) . "\n";
        $i++;
    } while ($i < count($fruits));
}

echo "\n== 5. Why the guard matters ==\n";
// Repeat all four against an EMPTY array and watch which one misbehaves.
$empty = [];

echo "foreach on []  : ";
foreach ($empty as $fruit) {
    echo "ran ";
}
echo "(body ran 0 times)\n";

echo "for on []      : ";
for ($i = 0; $i < count($empty); $i++) {
    echo "ran ";
}
echo "(body ran 0 times)\n";

echo "while on []    : ";
$i = 0;
while ($i < count($empty)) {
    echo "ran ";
    $i++;
}
echo "(body ran 0 times)\n";

echo "do...while on []: unguarded it would run ONCE and read \$empty[0],\n";
echo "                  which does not exist -> 'Undefined array key' warning.\n";

echo "\n== 6. Where do...while genuinely wins ==\n";
// "Ask, then check" - the question must be asked at least once.
// (Using a fixed list here instead of real input so the file stays runnable.)
$attempts = ["maybe", "nope", "yes"];
$n = 0;

do {
    $answer = $attempts[$n];
    echo "Attempt " . ($n + 1) . ": $answer\n";
    $n++;
} while ($answer !== "yes" && $n < count($attempts));

echo "Stopped after $n attempt(s).\n";

echo "\n";
