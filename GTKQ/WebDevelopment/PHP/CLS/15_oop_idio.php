<?php
// Object oddities. Run this and read down the output.
// Companion to 13_oop_basics.php and 14_oop_more.php.
// Section 5 emits an Error ON PURPOSE; it is caught and printed.

echo "\n== 1. self:: vs static:: (late static binding) ==\n";
// self::  = the class where the line was WRITTEN
// static::= the class the call was actually MADE on

class Model
{
    public static function makeSelf(): self
    {
        return new self();              // always a Model
    }

    public static function makeStatic(): static
    {
        return new static();            // whatever class you called it on
    }
}

class User extends Model {}

echo "  User::makeSelf()   gives " . get_class(User::makeSelf()) . "\n";    // Model
echo "  User::makeStatic() gives " . get_class(User::makeStatic()) . "\n";  // User
echo "  Factory methods on a parent almost always want static::, not self::.\n";

echo "\n== 2. clone is SHALLOW ==\n";
// Properties are copied, but a property holding an object copies the HANDLE.

class Engine
{
    public int $hp = 100;
}

class Car
{
    public function __construct(public Engine $engine) {}
}

$a = new Car(new Engine());
$b = clone $a;
$b->engine->hp = 500;                   // reaching through the shared handle
echo "  after \$b->engine->hp = 500, \$a->engine->hp is {$a->engine->hp}\n";
echo "  The Car was cloned. The Engine inside it was not.\n";

class DeepCar
{
    public function __construct(public Engine $engine) {}

    public function __clone(): void
    {
        $this->engine = clone $this->engine;   // clone the inside too
    }
}

$c = new DeepCar(new Engine());
$d = clone $c;
$d->engine->hp = 500;
echo "  with __clone(), \$c->engine->hp stays {$c->engine->hp}\n";

echo "\n== 3. private methods are NOT polymorphic ==\n";
// A private method binds to the class that wrote it. Overriding does nothing.

class Base
{
    private function who(): string    { return "Base"; }
    protected function role(): string { return "Base"; }

    public function tell(): string
    {
        return "who() says {$this->who()}, role() says {$this->role()}";
    }
}

class Derived extends Base
{
    private function who(): string    { return "Derived"; }   // ignored!
    protected function role(): string { return "Derived"; }   // works
}

echo "  " . (new Derived())->tell() . "\n";
echo "  Base::tell() cannot see Derived::who() at all - private means private.\n";
echo "  Use protected when you intend a child to override it.\n";

echo "\n== 4. == and === on objects mean different things ==\n";

class Point
{
    public function __construct(public int $x, public int $y) {}
}

$p1 = new Point(1, 2);
$p2 = new Point(1, 2);                  // same values, different object
$p3 = $p1;                              // same object

var_dump($p1 == $p2);                   // true  - same class, equal properties
var_dump($p1 === $p2);                  // false - not the same instance
var_dump($p1 === $p3);                  // true  - one object, two names
echo "  For arrays, === means 'same contents'. For objects it means 'same thing'.\n";

echo "\n== 5. Typed properties start UNINITIALIZED, not null ==\n";

class Untyped { public $value; }        // old style - defaults to null
class Typed   { public string $value; } // typed, no default - no value at all

$u = new Untyped();
var_dump($u->value);                    // NULL - fine

$t = new Typed();
var_dump(isset($t->value));             // false
try {
    echo $t->value;
} catch (Error $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}
echo "  Give typed properties a default, or set them in the constructor.\n";

echo "\n== 6. Trait precedence: class beats trait beats parent ==\n";

trait Greets
{
    public function hello(): string { return "from the TRAIT"; }
}

class Parent1
{
    public function hello(): string { return "from the PARENT"; }
}

class UsesTrait extends Parent1
{
    use Greets;                         // trait wins over the parent
}

class OwnMethod extends Parent1
{
    use Greets;
    public function hello(): string { return "from the CLASS"; }   // class wins over both
}

echo "  " . (new UsesTrait())->hello() . "\n";
echo "  " . (new OwnMethod())->hello() . "\n";

// Two traits with the same method is a fatal error unless you resolve it:
trait Shouts { public function hello(): string { return "HELLO!"; } }

class Resolved
{
    use Greets, Shouts {
        Shouts::hello insteadof Greets;     // pick the winner
        Greets::hello as quietHello;        // keep the loser under a new name
    }
}

$r = new Resolved();
echo "  " . $r->hello() . " / " . $r->quietHello() . "\n";

echo "\n== 7. Children share the parent's static property ==\n";

class Counter
{
    public static int $count = 0;
}

class SubCounter extends Counter {}     // does NOT redeclare $count

SubCounter::$count = 5;
echo "  Counter::\$count is now " . Counter::$count . "\n";   // 5 - same storage

class OwnCounter extends Counter
{
    public static int $count = 0;       // redeclared = its own storage
}

OwnCounter::$count = 99;
echo "  after OwnCounter::\$count = 99, Counter::\$count is " . Counter::$count . "\n";
echo "  Inherited statics are shared unless the child declares its own.\n";

echo "\n== 8. Magic methods, confusingly called 'overloading' ==\n";
// PHP's docs say 'overloading', but this is nothing like Java's overloading.
// It is a catch-all for properties and methods that do not exist.

class Bag
{
    private array $data = [];

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? "(no such property)";
    }

    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function __call(string $name, array $args): string
    {
        return "you called $name(" . implode(", ", $args) . ")";
    }
}

$bag = new Bag();
$bag->colour = "red";                   // __set - $colour was never declared
echo "  colour: {$bag->colour}\n";      // __get
echo "  missing: {$bag->nothing}\n";    // __get again
echo "  " . $bag->anything(1, 2) . "\n";// __call
echo "  Convenient, but your IDE and static analysers cannot see any of it.\n";

echo "\n== 9. Copying an array does not copy the objects inside ==\n";
// Ties back to 10_array.php section 15 and 11_array_idio.php section 13.
$garage = [new Engine()];
$backup = $garage;                      // the ARRAY is copied
$backup[0]->hp = 999;                   // but the Engine inside is shared
echo "  \$garage[0]->hp is now {$garage[0]->hp}\n";
echo "  Copy-on-write protects the array, never the objects it holds.\n";

echo "\n";
