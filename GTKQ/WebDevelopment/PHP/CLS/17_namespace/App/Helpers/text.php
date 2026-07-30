<?php
// Namespaces hold functions and constants too, not just classes.
namespace App\Helpers;

const VERSION = "1.0";

function slugify(string $text): string
{
    // strtolower() and trim() are NOT defined in App\Helpers. PHP does not
    // find them here, so it falls back to the global namespace and uses the
    // built-in ones. That fallback happens for FUNCTIONS and CONSTANTS only.
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
}

function whereAmI(): string
{
    return __NAMESPACE__;               // "App\Helpers"
}

function makeError(): \RuntimeException
{
    // The leading \ is REQUIRED here. Without it PHP would look for
    // App\Helpers\RuntimeException and fail - class names never fall back.
    return new \RuntimeException("thrown from " . __NAMESPACE__);
}
