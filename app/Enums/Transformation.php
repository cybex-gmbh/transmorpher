<?php

namespace App\Enums;

use InvalidArgumentException;

enum Transformation: string
{
    case WIDTH = 'w';
    case HEIGHT = 'h';
    case FORMAT = 'f';
    case QUALITY = 'q';

    /**
     * @param string|int $value
     * @return string|int|false
     */
    public function validate(string|int $value): string|int|false
    {
        $valid = match ($this) {
            self::WIDTH,
            self::HEIGHT => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]),
            self::FORMAT => in_array($value, ImageFormat::getFormats()) ? $value : false,
            self::QUALITY => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]])
        };

        if (!$valid) {
            throw new InvalidArgumentException(sprintf('The provided value %s for the %s parameter is not valid.', $value, $this->name));
        }

        return $value;
    }

    /**
     * @param string $transformations
     * @return array|null
     */
    public static function arrayFromString(string $transformations): array|null
    {
        if (!$transformations) {
            return null;
        }

        $transformationsArray = null;
        $parameters = explode('+', $transformations);

        foreach ($parameters as $parameter) {
            [$key, $value] = explode('-', $parameter, 2);
            $transformationsArray[$key] = Transformation::tryFrom($key)?->validate($value);
        }

        return $transformationsArray;
    }
}
