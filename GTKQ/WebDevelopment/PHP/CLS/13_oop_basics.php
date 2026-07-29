<?php
// Objects and classes: the basics, using one family - Animal, Dog, Cat.
// A class is the blueprint. An object is one thing built from that blueprint.

echo "\n== 1. A class, and an object made from it ==\n";

class Animal
{
    protected string $name;             // a property

    public function __construct(string $name)   // runs on `new`
    {
        $this->name = $name;            // $this = "the object I am working on"
    }

    public function speak(): string     // a method
    {
        return "...";
    }

    public function introduce(): string
    {
        return "I am {$this->name} and I say {$this->speak()}";
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }
}

$blob = new Animal("Blob");             // instantiation
echo "  " . $blob->introduce() . "\n";  // -> reaches into the object

$other = new Animal("Nessie");          // a separate, independent object
echo "  " . $other->introduce() . "\n";

echo "\n== 2. Inheritance: extends ==\n";
// A child gets everything from its parent, then changes only what it needs.

class Dog extends Animal
{
    public function speak(): string     // overriding the parent's version
    {
        return "Woof";
    }
}

$rex = new Dog("Rex");
echo "  " . $rex->introduce() . "\n";
echo "  Dog never defines __construct() or introduce() - both come from Animal.\n";
echo "  But introduce() now calls Dog's speak(), not Animal's.\n";

echo "\n== 3. parent:: - add to the parent instead of replacing it ==\n";

class Cat extends Animal
{
    private bool $indoor;

    public function __construct(string $name, bool $indoor = true)
    {
        parent::__construct($name);     // let Animal set $name first
        $this->indoor = $indoor;        // then add what is ours
    }

    public function speak(): string
    {
        return "Meow";
    }

    public function introduce(): string
    {
        return parent::introduce() . ($this->indoor ? " (indoor)" : " (outdoor)");
    }
}

echo "  " . (new Cat("Tom"))->introduce() . "\n";
echo "  " . (new Cat("Alley", false))->introduce() . "\n";
echo "  A child constructor MUST call parent::__construct() itself.\n";

echo "\n== 4. Visibility: public, private, protected ==\n";
// public    - anyone
// protected - this class and its children   ($name)
// private   - this class only               ($indoor)

$tom = new Cat("Tom");
echo "  " . $tom->introduce() . "\n";   // public method - fine
// echo $tom->name;                     // fatal error - protected
// echo $tom->indoor;                   // fatal error - private
echo "  \$name is protected, so Cat and Dog can use it but outside code cannot.\n";
echo "  \$indoor is private, so it belongs to Cat alone.\n";

echo "\n== 5. One loop, three behaviours ==\n";
// Every one of these IS an Animal, so the same call works on all of them.
$animals = [new Dog("Rex"), new Cat("Tom"), new Animal("Blob")];
foreach ($animals as $animal) {
    echo "  " . $animal->introduce() . "\n";
}
echo "  introduce() was written once, but each animal speaks for itself.\n";

echo "\n== 6. Asking what an object is ==\n";
var_dump($rex instanceof Dog);          // true
var_dump($rex instanceof Animal);       // true - a Dog IS an Animal
var_dump($rex instanceof Cat);          // false
echo "  class:  " . get_class($rex) . "\n";
echo "  parent: " . get_parent_class($rex) . "\n";

echo "\n== 7. Objects are handles, not copies ==\n";
// Arrays copy on assignment (10_array.php section 15). Objects do NOT.
$a = new Dog("Original");

$b = $a;                                // both names point at the SAME dog
$b->rename("Renamed");
echo "  after \$b->rename(): " . $a->introduce() . "\n";   // $a changed too!
var_dump($a === $b);                    // true - one object, two names

$c = clone $a;                          // clone builds a genuinely separate dog
$c->rename("Clone");
echo "  after \$c->rename(): " . $a->introduce() . "\n";   // $a is untouched
var_dump($a === $c);                    // false - two objects

echo "\n";
