<?php

namespace Tests\v2\Unit;

use App\Enums\Transformation;
use App\Exceptions\InvalidTransformationFormatException;
use App\Exceptions\InvalidTransformationValueException;
use App\Exceptions\TransformationNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransformationTest extends TestCase
{
    #[Test]
    #[DataProvider('provideValidTransformationStrings')]
    public function parsesValidTransformationStrings(string $input, ?array $expectedArray): void
    {
        $this->assertEquals($expectedArray, Transformation::arrayFromString($input));
    }

    #[Test]
    #[DataProvider('provideInvalidTransformationStrings')]
    public function failsOnInvalidTransformationStrings(string $input, string $expectedException): void
    {
        $this->expectException($expectedException);

        Transformation::arrayFromString($input);
    }

    /**
     * Provides successful parser scenarios for Transformation::arrayFromString.
     */
    public static function provideValidTransformationStrings(): array
    {
        return [
            'quality' => [
                'input' => 'q-50',
                'expectedArray' => [
                    'q' => 50,
                ],
            ],

            'width' => [
                'input' => 'w-1920',
                'expectedArray' => [
                    'w' => 1920,
                ],
            ],

            'height' => [
                'input' => 'h-1080',
                'expectedArray' => [
                    'h' => 1080,
                ],
            ],

            'format' => [
                'input' => 'f-webp',
                'expectedArray' => [
                    'f' => 'webp',
                ],
            ],

            'page' => [
                'input' => 'p-1',
                'expectedArray' => [
                    'p' => 1,
                ],
            ],

            'ppi' => [
                'input' => 'ppi-100',
                'expectedArray' => [
                    'ppi' => 100,
                ],
            ],

            'multiple' => [
                'input' => 'f-png+w-200+h-150+q-35+p-1+ppi-100',
                'expectedArray' => [
                    'f' => 'png',
                    'w' => 200,
                    'h' => 150,
                    'q' => 35,
                    'p' => 1,
                    'ppi' => 100,
                ],
            ],

            'duplicate_key_uses_last_value' => [
                'input' => 'w-200+w-400',
                'expectedArray' => [
                    'w' => 400,
                ],
            ],

            'empty' => [
                'input' => '',
                'expectedArray' => null,
            ],
        ];
    }

    /**
     * Provides invalid parser scenarios for Transformation::arrayFromString.
     */
    public static function provideInvalidTransformationStrings(): array
    {
        return [
            'quality_non_integer' => [
                'input' => 'q-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'quality_out_of_upper_bounds' => [
                'input' => 'q-101',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'quality_out_of_lower_bounds' => [
                'input' => 'q-0',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'width_out_of_lower_bound' => [
                'input' => 'w--12',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'width_non_integer' => [
                'input' => 'w-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'height_out_of_lower_bound' => [
                'input' => 'h--12',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'height_non_integer' => [
                'input' => 'h-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'format_undefined' => [
                'input' => 'f-pdf',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'page_out_of_lower_bound' => [
                'input' => 'p--12',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'page_non_integer' => [
                'input' => 'p-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'ppi_out_of_lower_bound' => [
                'input' => 'ppi-1',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'ppi_non_integer' => [
                'input' => 'ppi-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'first_value_wrong' => [
                'input' => 'f-dsa+w-200+h-150+q-100',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'middle_value_wrong' => [
                'input' => 'f-png+w-200+h-abc+q-100',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'last_value_wrong' => [
                'input' => 'f-png+w-200+h-150+q-101',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'multiple_values_wrong' => [
                'input' => 'f-png+w-abc+h-150+q-101',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'first_key_wrong' => [
                'input' => 'foo-png+w-200+h-150+q-100',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'middle_key_wrong' => [
                'input' => 'f-png+w-200+foo-150+q-100',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'last_key_wrong' => [
                'input' => 'f-png+w-200+h-150+foo-100',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'multiple_keys_wrong' => [
                'input' => 'foo-png+w-200+bar-150+q-100',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'leading_plus' => [
                'input' => '+f-png',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'only_plus' => [
                'input' => '+',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'trailing_plus' => [
                'input' => 'w-123+',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'missing_minus' => [
                'input' => 'fpng+q-50',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'only_minus' => [
                'input' => '-',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'key_missing' => [
                'input' => '-png',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'value_missing' => [
                'input' => 'w-',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'value_float' => [
                'input' => 'q-1.5',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'value_leading_zero' => [
                'input' => 'q-0005',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'value_containing_exponent' => [
                'input' => 'w-1337e0',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'value_hex' => [
                'input' => 'h-0x539',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'value_underscore' => [
                'input' => 'h-10_1',
                'expectedException' => InvalidTransformationValueException::class,
            ],
        ];
    }
}
