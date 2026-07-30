<?php
// The namespace declaration must be the FIRST statement in the file.
// It is just a prefix: this class's real, full name is App\Models\User.
namespace App\Models;

class User
{
    public function __construct(private string $name) {}

    public function describe(): string
    {
        return "{$this->name} - a database record (" . __CLASS__ . ")";
    }
}
