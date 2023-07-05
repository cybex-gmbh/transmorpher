<?php

namespace App\Helpers;

class ValidationRegex
{
    /**
     * Get the regex pattern for Media identifiers.
     * This regex allows lower/uppercase characters, numbers, underscores and hyphens.
     * Leading or trailing hyphens are disallowed.
     *
     * @return string
     */
    public static function forIdentifier(): string
    {
        return '/^[\w](?:-?[\w]+)*$/';
    }

    /**
     * Get the regex pattern for usernames.
     * This regex allows lower/uppercase characters, numbers, underscores and hyphens.
     * Leading or trailing hyphens are disallowed.
     *
     * @return string
     */
    public static function forUsername(): string
    {
        return static::forIdentifier();
    }
}
