<?php
// Beyond the basics: abstract classes, interfaces, traits, static members.
// Continues the Animal family from 13_oop_basics.php.

echo "\n== 1. abstract: a blueprint that cannot be built ==\n";

abstract class Animal
{
    public const KINGDOM = "Animalia";       // a class constant - fixed forever
    public static int $count = 0;            // ONE variable shared by all animals

    public function __construct(protected string $name)
    {
        self::$count++;
    }

    abstract public function speak(): string;   // no body - children MUST supply one

    public function introduce(): string
    {
        return "I am {$this->name} and I say {$this->speak()}";
    }

    public static function total(): int
    {
        return self::$count;
    }
}

class Dog extends Animal
{
    public function speak(): string { return "Woof"; }
}

class Cat extends Animal
{
    public function speak(): string { return "Meow"; }
}

$rex = new Dog("Rex");
$tom = new Cat("Tom");
echo "  " . $rex->introduce() . "\n";
echo "  " . $tom->introduce() . "\n";
// new Animal("Blob");                  // Fatal error: cannot instantiate abstract class
echo "  Animal has no sensible speak(), so it refuses to be instantiated.\n";
echo "  A child that forgets speak() will not even compile.\n";

echo "\n== 2. Class constants and static members ==\n";
// :: belongs to the CLASS. -> belongs to the OBJECT.
echo "  constant via class:  " . Animal::KINGDOM . "\n";
echo "  constant via object: " . $rex::KINGDOM . "\n";
echo "  animals created so far: " . Animal::total() . "\n";
new Dog("Buddy");
echo "  after one more:         " . Animal::total() . "\n";
echo "  \$count lives on the class, so every animal shares the same counter.\n";

echo "\n== 3. interface: a contract with no code ==\n";

interface Feedable
{
    public function feed(): string;      // signature only
}

interface Trainable
{
    public function learn(string $trick): string;
}

class Parrot extends Animal implements Feedable, Trainable
{
    private array $tricks = [];

    public function speak(): string { return "Squawk"; }

    public function feed(): string
    {
        return "{$this->name} eats seeds";
    }

    public function learn(string $trick): string
    {
        $this->tricks[] = $trick;
        return "{$this->name} now knows: " . implode(", ", $this->tricks);
    }
}

$polly = new Parrot("Polly");
echo "  " . $polly->feed() . "\n";
echo "  " . $polly->learn("wave") . "\n";
echo "  " . $polly->learn("whistle") . "\n";
echo "  One extends (single), many implements (as many as you like).\n";

echo "\n== 4. Programming to the contract ==\n";
// Type-hint the interface, not the class - then anything that honours it fits.

class Fish extends Animal implements Feedable
{
    public function speak(): string { return "Blub"; }
    public function feed(): string  { return "{$this->name} eats flakes"; }
}

function feedAll(Feedable ...$creatures): void
{
    foreach ($creatures as $creature) {
        echo "  " . $creature->feed() . "\n";
    }
}

feedAll($polly, new Fish("Nemo"));
// feedAll($rex);                       // TypeError - Dog does not implement Feedable
var_dump($polly instanceof Feedable);   // true
var_dump($polly instanceof Animal);     // true - it is both
var_dump($rex instanceof Feedable);     // false
echo "  feedAll() never mentions Parrot or Fish - it only asks for Feedable.\n";

echo "\n== 5. trait: shared code without inheritance ==\n";
// A trait is copied into the class. Use it when unrelated classes need the
// same method body but do not belong under the same parent.

trait Fetches
{
    public function fetch(): string
    {
        return "{$this->name} fetches the ball";   // may use the class's own properties
    }
}

class Retriever extends Dog
{
    use Fetches;
}

echo "  " . (new Retriever("Goldie"))->fetch() . "\n";
echo "  The trait reads \$this->name even though it does not declare it.\n";

echo "\n== 6. The trait + interface idiom ==\n";
// A trait gives you the code but is NOT a type. An interface is a type but
// has no code. Pair them: interface for the contract, trait for the body.

interface Swimmer
{
    public function swim(): string;
}

trait CanSwim
{
    public function swim(): string
    {
        return "{$this->name} paddles along";
    }
}

class Otter extends Animal implements Swimmer
{
    use CanSwim;
    public function speak(): string { return "Chirp"; }
}

class Duck extends Animal implements Swimmer
{
    use CanSwim;                        // same body, no shared parent needed
    public function speak(): string { return "Quack"; }
}

foreach ([new Otter("Ollie"), new Duck("Donald")] as $s) {
    echo "  " . $s->swim() . "\n";
}
var_dump((new Otter("Ollie")) instanceof Swimmer);   // true
// $ollie instanceof CanSwim;           // Fatal error - a trait is not a type
echo "  instanceof works on the interface, never on the trait.\n";

echo "\n== 7. final: stop here ==\n";

final class Sphynx extends Cat
{
    public function speak(): string { return "Mrrp"; }
}

echo "  " . (new Sphynx("Bald Bob"))->introduce() . "\n";
// class Kitten extends Sphynx {}       // Fatal error - Sphynx is final
echo "  final class  = nobody may extend it.\n";
echo "  final method = children may inherit it but not override it.\n";

echo "\n";
