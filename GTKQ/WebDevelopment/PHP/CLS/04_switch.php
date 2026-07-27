<?php
echo "Enter a day number (1 = Monday ... 7 = Sunday): ";

$day = (int) trim(fgets(STDIN));

switch ($day) {
    case 1:
        $name = "Monday";
        break;
    case 2:
        $name = "Tuesday";
        break;
    case 3:
        $name = "Wednesday";
        break;
    case 4:
        $name = "Thursday";
        break;
    case 5:
        $name = "Friday";
        break;
    case 6:
        $name = "Saturday";
        break;
    case 7:
        $name = "Sunday";
        break;
    default:
        $name = "not a valid day";
}

echo "Day $day is $name.\n";

// Cases with no body "fall through" to the next one, so several values
// can share a single block. Here 6 and 7 both run the weekend branch.
switch ($day) {
    case 6:
    case 7:
        echo "It is the weekend.\n";
        break;
    case 1:
    case 2:
    case 3:
    case 4:
    case 5:
        echo "It is a working day.\n";
        break;
    default:
        echo "Nothing to say about that day.\n";
}

// switch also works on strings.
echo "Enter a colour (red / green / blue): ";
$colour = strtolower(trim(fgets(STDIN)));

switch ($colour) {
    case "red":
        echo "Stop.\n";
        break;
    case "green":
        echo "Go.\n";
        break;
    case "blue":
        echo "Calm.\n";
        break;
    default:
        echo "I do not know that colour.\n";
}
