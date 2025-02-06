<?php

namespace App\Enums;

use App\Exceptions\InvalidTransformationValueException;
use App\Exceptions\InvalidTransformationFormatException;
use App\Exceptions\TransformationNotFoundException;
use ErrorException;
use ValueError;

enum Transformation: string
{
    case WIDTH = 'w';
    case HEIGHT = 'h';
    case FORMAT = 'f';
    case PAGE = 'p';
    case QUALITY = 'q';

    /**
     * @param string|int $value
     * @return string|int
     * @throws InvalidTransformationValueException
     */
    public function validate(string|int $value): string|int
    {
        $valid = match ($this) {
            self::WIDTH,
            self::HEIGHT,
            self::PAGE => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]),
            self::FORMAT => in_array($value, ImageFormat::getFormats(), true),
            self::QUALITY => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]),
        };

        if (!$valid) {
            throw new InvalidTransformationValueException($value, $this->name);
        }

        return $value;
    }

    /**
     * @param string $transformations
     * @return array|null
     * @throws InvalidTransformationValueException
     * @throws InvalidTransformationFormatException
     * @throws TransformationNotFoundException
     */
    public static function arrayFromString(string $transformations): array|null
    {
        if (!$transformations) {
            return null;
        }

        $transformationsArray = null;
        $parameters = explode('+', $transformations);

        foreach ($parameters as $parameter) {
            if (!$parameter) {
                throw new InvalidTransformationFormatException();
            }

            try {
                [$key, $value] = explode('-', $parameter, 2);
            } catch (ErrorException $exception) {
                throw new InvalidTransformationFormatException();
            }

            try {
                $transformationsArray[$key] = Transformation::from($key)->validate($value);
            } catch (ValueError $error) {
                throw new TransformationNotFoundException($key);
            }
        }

        return $transformationsArray;
    }
}
