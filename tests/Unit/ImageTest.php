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
            'validQualityTransformation' => [
                'requestedTransformations' => 'q-50',
                'expectedException' => null,
                'expectedArray' => [
                    'q' => 50,
                ]
            ],

            'invalidQualityTransformationNonInteger' => [
                'requestedTransformations' => 'q-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalidQualityTransformationOutOfUpperBounds' => [
                'requestedTransformations' => 'q-101',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalidQualityTransformationOutOfLowerBounds' => [
                'requestedTransformations' => 'q-0',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'validWidthTransformation' => [
                'requestedTransformations' => 'w-1920',
                'expectedException' => null,
                'expectedArray' => [
                    'w' => 1920,
                ]
            ],

            'invalidWidthTransformationOutOfLowerBound' => [
                'requestedTransformations' => 'w--12',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalidWidthTransformationNonInteger' => [
                'requestedTransformations' => 'w-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'validHeightTransformation' => [
                'requestedTransformations' => 'h-1080',
                'expectedException' => null,
                'expectedArray' => [
                    'h' => 1080,
                ]
            ],

            'invalidHeightTransformationOutOfLowerBound' => [
                'requestedTransformations' => 'h--12',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalidHeightTransformationNonInteger' => [
                'requestedTransformations' => 'h-aa',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'validFormatTransformation' => [
                'requestedTransformations' => 'f-webp',
                'expectedException' => null,
                'expectedArray' => [
                    'f' => 'webp',
                ]
            ],

            'invalidFormatTransformationNonAlphabetic' => [
                'requestedTransformations' => 'f-123',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalidFormatTransformationUndefinedFormat' => [
                'requestedTransformations' => 'f-pdf',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'validWithMultipleTransformations' => [
                'requestedTransformations' => 'f-png+w-200+h-150+q-35',
                'expectedException' => null,
                'expectedArray' => [
                    'f' => 'png',
                    'w' => 200,
                    'h' => 150,
                    'q' => 35,
                ]
            ],

            'invalidWithMultipleTransformations' => [
                'requestedTransformations' => 'f-png+w-200+h-150+q-101',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalidFormatLeadingPlus' => [
                'requestedTransformations' => '+',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'invalidFormatMissingMinus' => [
                'requestedTransformations' => 'abc+q-50',
                'expectedException' => InvalidTransformationFormatException::class,
            ],

            'invalidLeadingMinus' => [
                'requestedTransformations' => '-',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'invalidMissingTransformation' => [
                'requestedTransformations' => '-png',
                'expectedException' => TransformationNotFoundException::class,
            ],

            'invalidMissingValue' => [
                'requestedTransformations' => 'w-',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'empty' => [
                'requestedTransformations' => '',
                'exceptedException' => null,
                'expectedArray' => null
            ],

            'invalidFloat' => [
                'requestedTransformations' => 'q-1.5',
                'exceptedException' => InvalidTransformationValueException::class,
            ],

            'invalidLeadingZero' => [
                'requestedTransformations' => 'q-0005',
                'exceptedException' => InvalidTransformationValueException::class,
            ],

            'invalidContainingExponent' => [
                'requestedTransformations' => 'w-1337e0',
                'exceptedException' => InvalidTransformationValueException::class,
            ],

            'invalidHex' => [
                'requestedTransformations' => 'h-0x539',
                'exceptedException' => InvalidTransformationValueException::class,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider provideTransformationStrings
     */
    public function ensureTransformationStringsAreProperlyParsed(string $requestedTransformations, ?string $expectedException, ?array $expectedArray = null)
    {
        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $this->assertEquals($expectedArray, Transformation::arrayFromString($requestedTransformations));
    }
}
