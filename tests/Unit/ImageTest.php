<?php

namespace Tests\Unit;

use App\Enums\Transformation;
use App\Exceptions\InvalidTransformationFormatException;
use App\Exceptions\InvalidTransformationValueException;
use App\Exceptions\TransformationNotFoundException;
use App\Models\Media;
use FilePathHelper;
use Illuminate\Http\UploadedFile;
use Storage;
use Tests\MediaTest;

class ImageTest extends MediaTest
{
    protected const IDENTIFIER = 'testImage';
    protected const IMAGE_NAME = 'image.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::persistentFake(config('transmorpher.disks.imageDerivatives'));
    }

    /**
     * @test
     */
    public function ensureImageUploadSlotCanBeReserved()
    {
        $reserveUploadSlotResponse = $this->json('POST', route('v1.reserveImageUploadSlot'), [
            'identifier' => self::IDENTIFIER
        ]);
        $reserveUploadSlotResponse->assertOk();

        return $reserveUploadSlotResponse->json()['upload_token'];
    }

    /**
     * @test
     * @depends ensureImageUploadSlotCanBeReserved
     */
    public function ensureImageCanBeUploaded(string $uploadToken)
    {
        $uploadResponse = $this->json('POST', route('v1.upload', [$uploadToken]), [
            'file' => UploadedFile::fake()->image(self::IMAGE_NAME),
            'identifier' => self::IDENTIFIER
        ]);

        $uploadResponse->assertCreated();

        Storage::disk(config('transmorpher.disks.originals'))->assertExists(
            FilePathHelper::toOriginalFile(Media::whereIdentifier(self::IDENTIFIER)->first()->Versions()->whereNumber($uploadResponse['version'])->first()),
        );
    }

    /**
     * @test
     * @depends ensureImageCanBeUploaded
     */
    public function ensureProcessedFilesAreAvailable()
    {
        $media = self::$user->Media()->whereIdentifier(self::IDENTIFIER)->first();
        $getDerivativeResponse = $this->get(route('getDerivative', [self::$user->name, $media]));

        $getDerivativeResponse->assertOk();

        return $media;
    }

    /**
     * @test
     * @depends ensureProcessedFilesAreAvailable
     */
    public function ensureUnprocessedFilesAreNotAvailable(Media $media)
    {
        $media->Versions()->first()->update(['processed' => 0]);
        $getDerivativeResponse = $this->get(route('getDerivative', [self::$user->name, $media]));

        $getDerivativeResponse->assertNotFound();
    }

    /**
     * Provides Transformation string scenarios. contextually used in web requests for retrieving derivatives.
     *
     * @return array
     */
    public static function provideTransformationStrings(): array
    {
        return [
            'valid_Quality' => [
                'input' => 'q-50',
                'expectedException' => null,
                'expectedArray' => [
                    'q' => 50,
                ]
            ],

            'invalid_QualityNonInteger' => [
                'input' => 'q-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_QualityOutOfUpperBounds' => [
                'input' => 'q-101',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_QualityOutOfLowerBounds' => [
                'input' => 'q-0',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'valid_Width' => [
                'input' => 'w-1920',
                'expectedException' => null,
                'expectedArray' => [
                    'w' => 1920,
                ]
            ],

            'invalid_WidthOutOfLowerBound' => [
                'input' => 'w--12',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_WidthNonInteger' => [
                'input' => 'w-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'valid_Height' => [
                'input' => 'h-1080',
                'expectedException' => null,
                'expectedArray' => [
                    'h' => 1080,
                ]
            ],

            'invalid_HeightOutOfLowerBound' => [
                'input' => 'h--12',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_HeightNonInteger' => [
                'input' => 'h-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'valid_Format' => [
                'input' => 'f-webp',
                'expectedException' => null,
                'expectedArray' => [
                    'f' => 'webp',
                ]
            ],

            'invalid_FormatUndefined' => [
                'input' => 'f-pdf',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'valid_Multiple' => [
                'input' => 'f-png+w-200+h-150+q-35',
                'expectedException' => null,
                'expectedArray' => [
                    'f' => 'png',
                    'w' => 200,
                    'h' => 150,
                    'q' => 35,
                ]
            ],

            'invalid_FirstValueWrong' => [
                'input' => 'f-dsa+w-200+h-150+q-100',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_MiddleValueWrong' => [
                'input' => 'f-png+w-200+h-abc+q-100',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_LastValueWrong' => [
                'input' => 'f-png+w-200+h-150+q-101',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_MultipleValuesWrong' => [
                'input' => 'f-png+w-abc+h-150+q-101',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_FirstKeyWrong' => [
                'input' => 'foo-png+w-200+h-150+q-100',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'invalid_MiddleKeyWrong' => [
                'input' => 'f-png+w-200+foo-150+q-100',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'invalid_LastKeyWrong' => [
                'input' => 'f-png+w-200+h-150+foo-100',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'invalid_MultipleKeysWrong' => [
                'input' => 'foo-png+w-200+bar-150+q-100',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'invalid_LeadingPlus' => [
                'input' => '+f-png',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'invalid_OnlyPlus' => [
                'input' => '+',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'invalid_TrailingPlus' => [
                'input' => 'w-123+',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'invalid_MissingMinus' => [
                'input' => 'fpng+q-50',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'invalid_OnlyMinus' => [
                'input' => '-',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'invalid_KeyMissing' => [
                'input' => '-png',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'invalid_ValueMissing' => [
                'input' => 'w-',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'empty' => [
                'input' => '',
                'exceptedException' => null,
                'expectedArray' => null
            ],

            'invalid_ValueFloat' => [
                'input' => 'q-1.5',
                'exceptedException' => InvalidTransformationValueException::class,
            ],

            'invalid_ValueLeadingZero' => [
                'input' => 'q-0005',
                'exceptedException' => InvalidTransformationValueException::class,
            ],

            'invalid_ValueContainingExponent' => [
                'input' => 'w-1337e0',
                'exceptedException' => InvalidTransformationValueException::class,
            ],

            'invalid_ValueHex' => [
                'input' => 'h-0x539',
                'exceptedException' => InvalidTransformationValueException::class,
            ],

            'invalid_ValueUnderscore' => [
                'input' => 'h-10_1',
                'exceptedException' => InvalidTransformationValueException::class,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider provideTransformationStrings
     */
    public function ensureTransformationStringsAreProperlyParsed(string $input, ?string $expectedException, ?array $expectedArray = null)
    {
        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $this->assertEquals($expectedArray, Transformation::arrayFromString($input));
    }
}
