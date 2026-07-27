<?php
echo "Enter a score (0-100): ";

// Read one line from the terminal. trim() removes the trailing newline.
$input = trim(fgets(STDIN));

// Everything typed at a terminal arrives as a string, so convert it.
$score = (int) $input;

echo "You entered: $score\n";

if ($score >= 90) {
    $grade = "A";
} elseif ($score >= 80) {
    $grade = "B";
} elseif ($score >= 70) {
    $grade = "C";
} elseif ($score >= 50) {
    $grade = "D";
} else {
    $grade = "F";
}

echo "Grade: $grade\n";

// if / else can also test other things: strings, emptiness, ranges.
if ($score < 0 || $score > 100) {
    echo "Note: that is outside the normal 0-100 range.\n";
} elseif ($grade === "F") {
    echo "Better luck next time.\n";
} else {
    echo "Well done!\n";
}
