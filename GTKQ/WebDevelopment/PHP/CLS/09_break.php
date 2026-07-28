<?php
// break and continue: leaving a loop early, or skipping one round.

$fruits = ["apple", "banana", "cherry", "date", "elderberry"];

echo "\n== 1. break - stop as soon as you are done ==\n";
// Typical search: once found, there is no reason to keep looking.
foreach ($fruits as $i => $fruit) {
    echo "  checking $fruit\n";
    if ($fruit === "cherry") {
        echo "  found it at index $i - stopping\n";
        break;
    }
}

echo "\n== 2. continue - skip this one, carry on ==\n";
// Jumps straight to the next round; the rest of the body is skipped.
foreach ($fruits as $fruit) {
    if (strlen($fruit) > 6) {
        continue;               // too long, skip it
    }
    echo "  keeping $fruit\n";
}

echo "\n== 3. break 2 / continue 2 in nested loops ==\n";
// Plain break only leaves the INNER loop. The number says how many
// levels to leave, counting outwards from where you are.
$grid = [[1, 2, 3], [4, 0, 6], [7, 8, 9]];

foreach ($grid as $r => $row) {
    foreach ($row as $c => $cell) {
        if ($cell === 0) {
            echo "  zero found at row $r, col $c - leaving BOTH loops\n";
            break 2;
        }
        echo "  cell $cell\n";
    }
}

echo "\n  continue 2 - skip the rest of the outer round:\n";
foreach ($grid as $r => $row) {
    foreach ($row as $cell) {
        if ($cell === 0) {
            echo "  row $r has a zero - abandoning this row\n";
            continue 2;         // next ROW, not next cell
        }
    }
    echo "  row $r is clean\n";
}

echo "\n== 4. GOTCHA: break inside a switch inside a loop ==\n";
// switch counts as a breakable structure, so a plain 'break' in a case
// only leaves the SWITCH. To leave the loop you need break 2.
foreach ($fruits as $fruit) {
    switch ($fruit) {
        case "cherry":
            echo "  cherry: plain 'break' here only exits the switch\n";
            break;              // loop keeps going
        case "date":
            echo "  date: 'break 2' exits the switch AND the loop\n";
            break 2;
        default:
            echo "  $fruit: nothing special\n";
    }
}
echo "  The same applies to continue - inside a switch it behaves like\n";
echo "  break, so you almost always want 'continue 2' there.\n";

echo "\n== 5. continue in a while loop can hang forever ==\n";
// In a for loop the step still runs. In a while loop it does NOT,
// because the increment lives in the body you just skipped.
echo "  for  : continue is safe, \$i++ is in the loop head\n";
for ($i = 0; $i < 5; $i++) {
    if ($i === 2) {
        continue;
    }
    echo "    i = $i\n";
}
echo "  while: this shape never terminates -\n";
echo "         \$i = 0; while (\$i < 5) { if (\$i == 2) continue; ... \$i++; }\n";
echo "         because continue jumps back BEFORE \$i++ runs.\n";
echo "  Fix: increment before the continue, or use for.\n";

echo "\n== 6. The level must be a literal number ==\n";
// break 2;  is fine.   break $n;  is a fatal error - it was removed in PHP 5.4.
echo "  break 2;   OK\n";
echo "  break \$n;  Fatal error - the level cannot be a variable\n";

echo "\n";
