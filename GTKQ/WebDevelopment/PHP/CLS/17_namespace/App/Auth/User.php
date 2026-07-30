<?php
// A DIFFERENT class, also called User. Without namespaces one of these two
// files could not be loaded alongside the other.
namespace App\Auth;

class User
{
    public function __construct(private string $token) {}

    public function describe(): string
    {
        return "session {$this->token} - a logged-in visitor (" . __CLASS__ . ")";
    }
}
