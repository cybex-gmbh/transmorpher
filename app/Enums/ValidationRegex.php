<?php

namespace App\Enums;

enum ValidationRegex: string
{
    case IDENTIFIER = 'identifier';
    case USERNAME = 'username';

    /**
     * Get the regex pattern for a case.
     *
     * @return string
     */
    public function get(): string
    {
        return match ($this) {
            // This regex allows lower/uppercase characters, numbers, underscores and hyphens.
            // Leading or trailing hyphens are disallowed.
            self::IDENTIFIER,
            self::USERNAME => '/^\w(-?\w)*$/',
        };
    }
}
