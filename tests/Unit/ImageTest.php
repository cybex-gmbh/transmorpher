<?php

namespace Tests\Unit;

use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Exceptions\InvalidTransformationFormatException;
use App\Exceptions\InvalidTransformationValueException;
use App\Exceptions\TransformationNotFoundException;
use App\Models\Media;
use App\Models\Version;
use FilePathHelper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Storage;
use Tests\MediaTest;

class ImageTest extends MediaTest
{
    protected const IDENTIFIER = 'testImage';
    protected const IMAGE_NAME = 'image.jpg';
    protected Filesystem $imageDerivativesDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageDerivativesDisk ??= Storage::persistentFake(config(sprintf('transmorpher.disks.%s', MediaStorage::IMAGE_DERIVATIVES->value)));
    }

    #[Test]
    public function ensureImageUploadSlotCanBeReserved()
    {
        $reserveUploadSlotResponse = $this->json('POST', route('v1.reserveImageUploadSlot'), [
            'identifier' => self::IDENTIFIER
        ]);
        $reserveUploadSlotResponse->assertOk();

        return $reserveUploadSlotResponse->json()['upload_token'];
    }

    #[Test]
    #[Depends('ensureImageUploadSlotCanBeReserved')]
    public function ensureImageCanBeUploaded(string $uploadToken)
    {
        $uploadResponse = $this->json('POST', route('v1.upload', [$uploadToken]), [
            'file' => UploadedFile::fake()->image(self::IMAGE_NAME),
            'identifier' => self::IDENTIFIER
        ]);

        $uploadResponse->assertCreated();

        $media = Media::whereIdentifier(self::IDENTIFIER)->first();
        $version = $media->Versions()->whereNumber($uploadResponse['version'])->first();

        Storage::disk(config('transmorpher.disks.originals'))->assertExists(
            FilePathHelper::toOriginalFile($version),
        );

        return $version;
    }

    #[Test]
    #[Depends('ensureImageCanBeUploaded')]
    public function ensureProcessedFilesAreAvailable(Version $version)
    {
        $getDerivativeResponse = $this->get(route('getDerivative', [self::$user->name, $version->Media]));

        $getDerivativeResponse->assertOk();

        return $version;
    }

    #[Test]
    #[Depends('ensureProcessedFilesAreAvailable')]
    public function ensureUnprocessedFilesAreNotAvailable(Version $version)
    {
        $version->update(['processed' => 0]);
        $getDerivativeResponse = $this->get(route('getDerivative', [self::$user->name, $version->Media]));

        $getDerivativeResponse->assertNotFound();

        return $version;
    }

    /**
     * @param Version $version
     * @return void
     */
    public function assertVersionFilesExist(Version $version): void
    {
        $this->originalsDisk->assertExists(FilePathHelper::toOriginalFile($version));
        $this->imageDerivativesDisk->assertExists(FilePathHelper::toImageDerivativeFile($version));
    }

    /**
     * @param $media
     * @return void
     */
    public function assertMediaFilesExist($media): void
    {
        $this->originalsDisk->assertExists(FilePathHelper::toBaseDirectory($media));
    }

    /**
     * @return void
     */
    public function assertUserFilesExist(): void
    {
        $this->originalsDisk->assertExists(self::$user->name);
        $this->imageDerivativesDisk->assertExists(self::$user->name);
    }

    /**
     * @param Version $version
     * @return void
     */
    public function assertVersionFilesMissing(Version $version): void
    {
        $this->originalsDisk->assertMissing(FilePathHelper::toOriginalFile($version));
        $this->imageDerivativesDisk->assertMissing(FilePathHelper::toImageDerivativeFile($version));
    }

    /**
     * @param $media
     * @return void
     */
    public function assertMediaFilesMissing($media): void
    {
        $this->originalsDisk->assertMissing(FilePathHelper::toBaseDirectory($media));
        $this->imageDerivativesDisk->assertMissing(FilePathHelper::toBaseDirectory($media));
    }

    /**
     * @return void
     */
    public function assertUserFilesMissing(): void
    {
        $this->originalsDisk->assertMissing(self::$user->name);
        $this->imageDerivativesDisk->assertMissing(self::$user->name);
    }

    #[Test]
    #[Depends('ensureUnprocessedFilesAreNotAvailable')]
    public function ensureVersionDeletionMethodsWork(Version $version)
    {
        $this->assertVersionFilesExist($version);
        $this->runProtectedMethod($version, 'deleteFiles');
        $this->assertVersionFilesMissing($version);
    }

    #[Test]
    #[Depends('ensureVersionDeletionMethodsWork')]
    public function ensureMediaDeletionMethodsWork()
    {
        $uploadToken = $this->ensureImageUploadSlotCanBeReserved();
        // Upload a new version.
        $version = $this->ensureImageCanBeUploaded($uploadToken);
        // Create a derivative
        $this->ensureProcessedFilesAreAvailable($version);
        $media = $version->Media;

        $this->assertVersionFilesExist($version);
        $this->assertMediaFilesExist($media);

        $this->runProtectedMethod($media, 'deleteRelatedModels');
        $this->runProtectedMethod($media, 'deleteBaseDirectories');

        $this->assertVersionFilesMissing($version);
        $this->assertMediaFilesMissing($media);
    }

    #[Test]
    #[Depends('ensureMediaDeletionMethodsWork')]
    public function ensureUserDeletionMethodsWork()
    {
        $uploadToken = $this->ensureImageUploadSlotCanBeReserved();
        // Upload a new version.
        $version = $this->ensureImageCanBeUploaded($uploadToken);
        // Create a derivative
        $this->ensureProcessedFilesAreAvailable($version);
        $media = $version->Media;

        $this->assertVersionFilesExist($version);
        $this->assertMediaFilesExist($media);
        $this->assertUserFilesExist();

        $this->runProtectedMethod(self::$user, 'deleteRelatedModels');
        $this->runProtectedMethod(self::$user, 'deleteMediaDirectories');

        $this->assertVersionFilesMissing($version);
        $this->assertMediaFilesMissing($media);
        $this->assertUserFilesMissing();
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
                'expectedException' => null,
                'expectedArray' => null
            ],

            'invalid_ValueFloat' => [
                'input' => 'q-1.5',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_ValueLeadingZero' => [
                'input' => 'q-0005',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_ValueContainingExponent' => [
                'input' => 'w-1337e0',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_ValueHex' => [
                'input' => 'h-0x539',
                'expectedException' => InvalidTransformationValueException::class,
            ],

            'invalid_ValueUnderscore' => [
                'input' => 'h-10_1',
                'expectedException' => InvalidTransformationValueException::class,
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideTransformationStrings')]
    public function ensureTransformationStringsAreProperlyParsed(string $input, ?string $expectedException, ?array $expectedArray = null)
    {
        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $this->assertEquals($expectedArray, Transformation::arrayFromString($input));
    }
}
