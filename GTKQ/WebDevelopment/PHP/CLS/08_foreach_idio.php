<?php
// foreach oddities. Run this and read down the output.
// Companion to 06_idiosyncrasies.php, but specific to looping.

function dump(string $label, array $arr): void
{
    printf("  %-22s %s\n", $label, json_encode($arr));
}

echo "\n== 1. By value: you get a COPY ==\n";
// $v is a copy of each element, so writing to it changes nothing.
$nums = [1, 2, 3];
foreach ($nums as $v) {
    $v *= 10;
}
dump("after \$v *= 10", $nums);
echo "  The array is untouched - a very common beginner surprise.\n";

echo "\n== 2. By reference: &\$v writes back ==\n";
// The & makes $v an alias for the real element.
$nums = [1, 2, 3];
foreach ($nums as &$v) {
    $v *= 10;
}
unset($v);                      // see section 3 for why
dump("after &\$v *= 10", $nums);

echo "\n== 3. THE classic bug: forgetting unset() ==\n";
// After a by-reference loop, $v still points at the LAST element.
// The next loop that reuses $v writes through that pointer.
$bad = ["a", "b", "c"];
foreach ($bad as &$v) {         // $v now aliases $bad[2]
    echo "  inside loop, \$v = $v\n";
}
echo "  \$v is still an alias for the last element: $v\n";
print_r($bad);
foreach ($bad as $v) {          // each round assigns into $bad[2]!
    echo "  inside loop, \$v = $v\n";
}
dump("without unset(\$v)", $bad);

$good = ["a", "b", "c"];
foreach ($good as &$v) {
    echo "  inside loop, \$v = $v\n";
}
unset($v);                      // break the alias
foreach ($good as $v) {
    echo "  inside loop, \$v = $v\n";
}
dump("with unset(\$v)", $good);
echo "  Rule: always unset() the variable after a by-reference foreach.\n";

echo "\n== 4. Keys are preserved, never renumbered ==\n";
// unset() leaves a hole. foreach walks the real keys, gaps and all.
$fruits = ["apple", "banana", "cherry"];
unset($fruits[1]);
echo "  keys now: ";
foreach ($fruits as $i => $fruit) {
    echo "$i=>$fruit  ";
}
echo "\n";
echo "  So \$i + 1 numbering (used in 07_loop.php) would print 1. then 3.\n";
echo "  Use array_values() to reindex, or a separate counter for display.\n";
dump("array_values()", array_values($fruits));

echo "\n== 5. Editing the array inside a by-value loop ==\n";
// foreach iterates over a snapshot, so appends are NOT visited.
$list = [1, 2, 3];
foreach ($list as $v) {
    $list[] = $v * 100;         // grows the array, but not the iteration
}
dump("ran 3 times, got", $list);
echo "  The loop ran 3 times, not forever - it used the original copy.\n";

echo "\n== 6. The same edit BY REFERENCE is a trap ==\n";
// With &$v, foreach follows the live array instead of a copy, so
// appending inside the loop keeps feeding it new elements = infinite loop.
// Not demonstrated here on purpose - it would hang this script.
echo "  foreach (\$list as &\$v) { \$list[] = ...; }  // never terminates\n";
echo "  If you must add while looping, build a second array instead.\n";

echo "\n== 7. Destructuring in the loop head ==\n";
// Handy for arrays of rows.
$pairs = [[1, "one"], [2, "two"], [3, "three"]];
foreach ($pairs as [$number, $word]) {
    echo "  $number = $word\n";
}

echo "\n";
