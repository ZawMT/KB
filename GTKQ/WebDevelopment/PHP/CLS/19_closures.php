<?php
// Closures and callables.
//   callable = anything PHP can invoke (several forms)
//   closure  = one of those forms: an anonymous function, which is an OBJECT

echo "\n== 1. A function stored in a variable ==\n";

$double = function (int $n): int {
    return $n * 2;
};

echo "  \$double(5) = " . $double(5) . "\n";
var_dump($double instanceof Closure);   // true - it really is an object
echo "  Note the semicolon after } - this is an assignment, not a declaration.\n";

echo "\n== 2. use: reaching outside the closure ==\n";
// A closure cannot see the surrounding scope unless you say so.

$tax = 0.2;

$withoutUse = function (float $price): string {
    return isset($tax) ? "sees \$tax" : "cannot see \$tax";
};
echo "  " . $withoutUse(100) . "\n";

$withTax = function (float $price) use ($tax): float {
    return $price * (1 + $tax);
};
echo "  100 with tax = " . $withTax(100) . "\n";
echo "  JavaScript and Python capture automatically. PHP makes you ask.\n";

echo "\n== 3. use captures BY VALUE, at definition time ==\n";

$rate = 10;
$byValue = function () use ($rate) { return $rate; };
$byRef   = function () use (&$rate) { return $rate; };
$rate = 99;                             // changed AFTER both were defined

echo "  by value: " . $byValue() . "   (frozen at 10)\n";
echo "  by ref:   " . $byRef()   . "   (follows the variable)\n";
echo "  This is the single most common closure surprise in PHP.\n";

echo "\n== 4. Arrow functions: fn() => ... ==\n";
// PHP 7.4+. Captures automatically, by value. One expression only.

$vat = 0.2;
$quick = fn(float $price): float => $price * (1 + $vat);   // no use needed
echo "  " . $quick(100) . "\n";

$n = 1;
$arrow = fn() => $n;
$n = 2;
echo "  still by value: " . $arrow() . " (not 2)\n";
echo "  No braces, no statements, implicit return - that is the trade-off.\n";
echo "  This is why array_map/filter callbacks are nearly always fn().\n";

echo "\n== 5. The forms of a callable ==\n";

function shout(string $s): string
{
    return strtoupper($s) . "!";
}

class Formatter
{
    public function pretty(string $s): string        { return "<< $s >>"; }
    public static function plain(string $s): string  { return "-- $s --"; }
    public function __invoke(string $s): string      { return "((( $s )))"; }
}

$fmt = new Formatter();

$callables = [
    "function name (string)" => 'shout',
    "closure"                => function ($s) { return "[$s]"; },
    "arrow function"         => fn($s) => "{{$s}}",
    "object + method"        => [$fmt, 'pretty'],
    "class + static method"  => [Formatter::class, 'plain'],
    "static as one string"   => 'Formatter::plain',
    "invokable object"       => $fmt,               // uses __invoke
    "first-class callable"   => shout(...),         // PHP 8.1+
];

foreach ($callables as $label => $c) {
    printf("  %-24s %s\n", $label, $c("hello"));
    if (!is_callable($c)) {
        echo "  (not callable?!)\n";
    }
}

echo "\n== 6. __invoke: an object that behaves like a function ==\n";
// Useful when the 'function' needs settings or state of its own.

class Multiplier
{
    public function __construct(private int $factor) {}

    public function __invoke(int $n): int
    {
        return $n * $this->factor;
    }
}

$triple = new Multiplier(3);
echo "  \$triple(7) = " . $triple(7) . "\n";
print_r(array_map($triple, [1, 2, 3]));     // works anywhere a callable fits
echo "  A closure with a constructor, essentially.\n";

echo "\n== 7. Taking and returning closures ==\n";

// Taking one:
function applyTwice(callable $f, $value)
{
    return $f($f($value));
}
echo "  applyTwice(double, 5) = " . applyTwice($double, 5) . "\n";

// Returning one - the closure keeps $count alive after makeCounter() ends:
function makeCounter(int $start = 0): Closure
{
    $count = $start;
    return function () use (&$count): int {
        return ++$count;
    };
}

$tickets = makeCounter(100);
echo "  " . $tickets() . ", " . $tickets() . ", " . $tickets() . "\n";
$other = makeCounter(100);
echo "  a second counter is independent: " . $other() . "\n";
echo "  &\$count is what makes the counter remember between calls.\n";
echo "  Type-hint Closure when you mean specifically a closure, callable otherwise.\n";

echo "\n== 8. Closures inside a class see \$this ==\n";

class Cart
{
    private array $prices = ["apple" => 2.50, "bread" => 3.00];

    private function taxRate(): float
    {
        return 1.1;
    }

    public function total(): float
    {
        // $this is available automatically - no `use ($this)` needed or allowed.
        return array_sum(array_map(
            fn(float $p): float => $p * $this->taxRate(),
            $this->prices
        ));
    }

    public function detached(): string
    {
        $f = static function () {           // `static` = no $this at all
            return isset($this) ? "has \$this" : "no \$this";
        };
        return $f();
    }
}

$cart = new Cart();
echo "  total: " . number_format($cart->total(), 2) . "\n";
echo "  inside a static closure: " . $cart->detached() . "\n";

echo "\n== 9. First-class callable syntax (PHP 8.1+) ==\n";
// The ... is literal. It turns a function or method into a Closure.

$upper  = strtoupper(...);              // instead of 'strtoupper'
$pretty = $fmt->pretty(...);            // instead of [$fmt, 'pretty']
$plain  = Formatter::plain(...);        // instead of 'Formatter::plain'

echo "  " . $upper("abc") . " " . $pretty("abc") . " " . $plain("abc") . "\n";
var_dump($upper instanceof Closure);    // true
echo "  Typos are caught at compile time, and the IDE can follow it.\n";
echo "  Prefer this over quoted function names in new code.\n";

echo "\n== 10. Where you already use these ==\n";
echo "  10_array.php  array_map / array_filter / usort all take a callable\n";
echo "  This is the THIRD meaning of `use`:\n";
echo "    use App\\Models\\User;   namespace alias   (17_namespace.php)\n";
echo "    use Fetches;            insert a trait    (14_oop_more.php)\n";
echo "    function () use (\$x)    capture into a closure  (this file)\n";

echo "\n";
