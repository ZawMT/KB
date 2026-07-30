<?php
// Loading this file twice with plain require would be a fatal error:
// "Cannot redeclare shout()". Hence require_once.

function shout(string $text): string
{
    return strtoupper($text) . "!";
}
