<?php
// This file is not a function or a class - it is just code that runs
// wherever it is included, sharing that spot's variables.

echo "  hello, $name\n";     // $name comes from whoever included this file
$greeted = true;             // and this leaks back out to them
