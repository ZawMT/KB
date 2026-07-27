<?php
echo "Enter a day number (1 = Monday ... 7 = Sunday): ";

$day = (int) trim(fgets(STDIN));

// match is an expression: it RETURNS a value, so it is assigned.
// No break needed - only the matching arm runs.
$name = match ($day) {
    1 => "Monday",
    2 => "Tuesday",
    3 => "Wednesday",
    4 => "Thursday",
    5 => "Friday",
    6 => "Saturday",
    7 => "Sunday",
    default => "not a valid day",
};

echo "Day $day is $name.\n";

// Several values can share one arm, separated by commas.
$kind = match ($day) {
    6, 7 => "the weekend",
    1, 2, 3, 4, 5 => "a working day",
    default => "unknown",
};

echo "That is $kind.\n";

// match compares strictly (===), so types must agree.
// "6" (string) would NOT match the arm 6 (int) above.

// match (true) replaces a long if / elseif chain: the first arm whose
// condition is true wins, so order matters - put the narrowest first.
echo "Enter a score (0-100): ";
$score = (int) trim(fgets(STDIN));

$grade = match (true) {
    $score >= 90 => "A",
    $score >= 80 => "B",
    $score >= 70 => "C",
    $score >= 50 => "D",
    default      => "F",
};

echo "Grade: $grade\n";

// Without a default arm, an unmatched value throws UnhandledMatchError
// instead of silently doing nothing (which is what switch would do).
try {
    $roman = match ($day) {
        1 => "I",
        2 => "II",
        3 => "III",
    };
    echo "Roman numeral: $roman\n";
} catch (\UnhandledMatchError $e) {
    echo "No arm matched $day: " . $e->getMessage() . "\n";
}
