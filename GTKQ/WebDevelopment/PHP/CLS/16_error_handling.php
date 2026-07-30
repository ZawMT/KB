<?php
// Error handling: try, catch, finally, throw.
// The idea: a function that cannot do its job says so loudly, and the
// caller decides what to do about it.

echo "\n== 1. try / catch ==\n";
// Without try, this line would stop the whole script.

try {
    $result = 10 / 0;                   // throws DivisionByZeroError in PHP 8
    echo "  never reached\n";
} catch (DivisionByZeroError $e) {
    echo "  caught: " . $e->getMessage() . "\n";
}
echo "  the script carries on\n";

echo "\n== 2. Throwing your own ==\n";

function setAge(int $age): string
{
    if ($age < 0) {
        throw new InvalidArgumentException("Age cannot be negative, got $age");
    }
    if ($age > 150) {
        throw new RangeException("Age $age is not plausible");
    }
    return "age set to $age";
}

foreach ([30, -5, 200] as $try) {
    try {
        echo "  " . setAge($try) . "\n";
    } catch (InvalidArgumentException | RangeException $e) {   // catch either
        echo "  rejected: " . $e->getMessage() . "\n";
    }
}
echo "  throw ends the function immediately - no return value comes back.\n";

echo "\n== 3. The hierarchy ==\n";
echo "  Throwable  (interface - catches absolutely everything)\n";
echo "    +- Error      : PHP itself is unhappy (TypeError, DivisionByZeroError...)\n";
echo "    +- Exception  : your program is unhappy (InvalidArgumentException...)\n";
echo "  Error and Exception are SIBLINGS, not parent and child.\n";

try {
    throw new TypeError("a broken type");
} catch (Exception $e) {                // does NOT match - TypeError is an Error
    echo "  caught by Exception\n";
} catch (Throwable $e) {
    echo "  caught by Throwable: " . get_class($e) . "\n";
}
echo "  catch (Exception) misses every Error. Use Throwable to catch both.\n";

echo "\n== 4. Order matters: specific first ==\n";

try {
    throw new InvalidArgumentException("bad input");
} catch (InvalidArgumentException $e) {     // most specific
    echo "  specific handler: " . $e->getMessage() . "\n";
} catch (Exception $e) {                    // fallback
    echo "  general handler - not reached\n";
}
echo "  PHP takes the FIRST matching catch, so put the broad one last.\n";

echo "\n== 5. finally always runs ==\n";

function readRecord(bool $fail): string
{
    echo "  opening the connection\n";
    try {
        if ($fail) {
            throw new RuntimeException("connection died");
        }
        return "got the record";
    } finally {
        echo "  closing the connection\n";   // runs on success AND on failure
    }
}

echo "  " . readRecord(false) . "\n";
try {
    readRecord(true);
} catch (RuntimeException $e) {
    echo "  caught: " . $e->getMessage() . "\n";
}
echo "  finally runs even after return or throw - use it for cleanup.\n";

echo "\n== 6. A custom exception ==\n";
// It is just a class that extends Exception, so it can carry extra data.

class InsufficientFundsException extends Exception
{
    public function __construct(private float $shortfall)
    {
        parent::__construct("Short by " . number_format($shortfall, 2));
    }

    public function getShortfall(): float
    {
        return $this->shortfall;
    }
}

function withdraw(float $balance, float $amount): float
{
    if ($amount > $balance) {
        throw new InsufficientFundsException($amount - $balance);
    }
    return $balance - $amount;
}

try {
    withdraw(50.00, 75.50);
} catch (InsufficientFundsException $e) {
    echo "  " . $e->getMessage() . "\n";
    echo "  top up by " . $e->getShortfall() . " and try again\n";
}
echo "  A custom type lets the caller catch THIS problem and no other.\n";

echo "\n== 7. What an exception knows ==\n";

try {
    throw new Exception("something went wrong", 42);
} catch (Exception $e) {
    echo "  message: " . $e->getMessage() . "\n";
    echo "  code:    " . $e->getCode() . "\n";
    echo "  file:    " . basename($e->getFile()) . "\n";
    echo "  line:    " . $e->getLine() . "\n";
}

echo "\n== 8. Wrapping one exception in another ==\n";
// Keep the original as the "previous" so you do not lose the real cause.

function loadConfig(): array
{
    try {
        throw new RuntimeException("disk unreadable");   // the low-level cause
    } catch (RuntimeException $e) {
        throw new LogicException("Could not load config", 0, $e);   // 3rd arg
    }
}

try {
    loadConfig();
} catch (LogicException $e) {
    echo "  outer: " . $e->getMessage() . "\n";
    echo "  cause: " . $e->getPrevious()->getMessage() . "\n";
}

echo "\n== 9. Warnings are NOT exceptions ==\n";
// Many older PHP functions do not throw - they warn and return false.
// The Warning below is expected.
$content = file_get_contents("no_such_file.txt");
var_dump($content);                     // false
echo "  try/catch would not have helped - nothing was thrown.\n";
echo "  Check the return value instead:\n";
if (($content = @file_get_contents("no_such_file.txt")) === false) {
    echo "  could not read the file\n";
}

// Modern alternatives usually offer a throwing mode:
try {
    json_decode("{not valid json}", true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    echo "  json: " . $e->getMessage() . "\n";
}

echo "\n== 10. Uncaught means fatal ==\n";
echo "  An uncaught throw prints 'PHP Fatal error: Uncaught ...' and stops.\n";
echo "  Catch what you can actually handle - let the rest crash loudly.\n";

echo "\n";
