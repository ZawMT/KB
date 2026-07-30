<?php
// Nothing in 17_namespace.php requires this file by hand.
// The autoloader in section 5 finds it from the class name alone.
namespace App\Models;

class Product
{
    public function __construct(public string $title, public float $price) {}

    public function label(): string
    {
        return "{$this->title} costs {$this->price}";
    }
}
