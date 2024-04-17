<?php

namespace Tests\Unit;

use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Exceptions\InvalidTransformationFormatException;
use App\Exceptions\InvalidTransformationValueException;
use App\Exceptions\TransformationNotFoundException;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\Version;
use FilePathHelper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
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
    protected string $uploadToken;
    protected Version $version;
    protected Media $media;
    protected UploadSlot $uploadSlot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageDerivativesDisk ??= Storage::persistentFake(config(sprintf('transmorpher.disks.%s', MediaStorage::IMAGE_DERIVATIVES->value)));
    }

    protected function reserveUploadSlot(): TestResponse
    {
        return $this->json('POST', route('v1.reserveImageUploadSlot'), [
            'identifier' => self::IDENTIFIER
        ]);
    }

    #[Test]
    public function ensureImageUploadSlotCanBeReserved()
    {
        $reserveUploadSlotResponse = $this->reserveUploadSlot();

        $reserveUploadSlotResponse->assertOk();

        return $reserveUploadSlotResponse->json()['upload_token'];
    }

    protected function uploadImage(string $uploadToken): TestResponse
    {
        return $this->json('POST', route('v1.upload', [$uploadToken]), [
            'file' => UploadedFile::fake()->image(self::IMAGE_NAME),
            'identifier' => self::IDENTIFIER
        ]);
    }

    #[Test]
    #[Depends('ensureImageUploadSlotCanBeReserved')]
    public function ensureImageCanBeUploaded(string $uploadToken)
    {
        $uploadResponse = $this->uploadImage($uploadToken);

        $uploadResponse->assertCreated();

        $media = Media::whereIdentifier(self::IDENTIFIER)->first();
        $version = $media->Versions()->whereNumber($uploadResponse['version'])->first();

        Storage::disk(config('transmorpher.disks.originals'))->assertExists(
            FilePathHelper::toOriginalFile($version),
        );

        return $version;
    }

    protected function createDerivativeForVersion(Version $version): TestResponse
    {
        return $this->get(route('getDerivative', [self::$user->name, $version->Media]));
    }

    #[Test]
    #[Depends('ensureImageCanBeUploaded')]
    public function ensureProcessedFilesAreAvailable(Version $version)
    {
        $this->createDerivativeForVersion($version)->assertOk();

        return $version;
    }

    #[Test]
    #[Depends('ensureProcessedFilesAreAvailable')]
    public function ensureUnprocessedFilesAreNotAvailable(Version $version)
    {
        $version->update(['processed' => 0]);
        $getDerivativeResponse = $this->get(route('getDerivative', [self::$user->name, $version->Media]));

        $getDerivativeResponse->assertNotFound();
    }

    protected function assertVersionFilesExist(Version $version): void
    {
        $this->originalsDisk->assertExists(FilePathHelper::toOriginalFile($version));
        $this->imageDerivativesDisk->assertExists(FilePathHelper::toImageDerivativeFile($version));
    }

    protected function assertMediaDirectoryExists($media): void
    {
        $this->originalsDisk->assertExists(FilePathHelper::toBaseDirectory($media));
    }

    protected function assertUserDirectoryExists(): void
    {
        $this->originalsDisk->assertExists(self::$user->name);
        $this->imageDerivativesDisk->assertExists(self::$user->name);
    }

    protected function assertVersionFilesMissing(Version $version): void
    {
        $this->originalsDisk->assertMissing(FilePathHelper::toOriginalFile($version));
        $this->imageDerivativesDisk->assertMissing(FilePathHelper::toImageDerivativeFile($version));
    }

    protected function assertMediaDirectoryMissing($media): void
    {
        $this->originalsDisk->assertMissing(FilePathHelper::toBaseDirectory($media));
        $this->imageDerivativesDisk->assertMissing(FilePathHelper::toBaseDirectory($media));
    }

    protected function assertUserDirectoryMissing(): void
    {
        $this->originalsDisk->assertMissing(self::$user->name);
        $this->imageDerivativesDisk->assertMissing(self::$user->name);
    }

    protected function setupDeletionTest(): void
    {
        $this->uploadToken = $this->reserveUploadSlot()->json()['upload_token'];
        $this->version = Media::whereIdentifier(self::IDENTIFIER)->first()->Versions()->whereNumber($this->uploadImage($this->uploadToken)['version'])->first();
        $this->createDerivativeForVersion($this->version);
        $this->media = $this->version->Media;
        $this->uploadSlot = UploadSlot::whereToken($this->uploadToken)->withoutGlobalScopes()->first();
    }

    #[Test]
    public function ensureVersionDeletionMethodsWork()
    {
        $this->setupDeletionTest();

        $this->assertVersionFilesExist($this->version);

        $this->runProtectedMethod($this->version, 'deleteFiles');

        $this->assertVersionFilesMissing($this->version);
    }

    #[Test]
    public function ensureMediaDeletionMethodsWork()
    {
        $this->setupDeletionTest();

        $this->assertVersionFilesExist($this->version);
        $this->assertMediaDirectoryExists($this->media);

        $this->runProtectedMethod($this->media, 'deleteRelatedModels');
        $this->runProtectedMethod($this->media, 'deleteBaseDirectories');

        $this->assertVersionFilesMissing($this->version);
        $this->assertMediaDirectoryMissing($this->media);

        $this->assertModelMissing($this->version);
        $this->assertModelMissing($this->uploadSlot);
    }

    #[Test]
    public function ensureUserDeletionMethodsWork()
    {
        $this->setupDeletionTest();

        $this->assertVersionFilesExist($this->version);
        $this->assertMediaDirectoryExists($this->media);
        $this->assertUserDirectoryExists();

        $this->runProtectedMethod(self::$user, 'deleteRelatedModels');
        $this->runProtectedMethod(self::$user, 'deleteMediaDirectories');

        $this->assertVersionFilesMissing($this->version);
        $this->assertMediaDirectoryMissing($this->media);
        $this->assertUserDirectoryMissing();

        $this->assertModelMissing($this->version);
        $this->assertModelMissing($this->media);
        $this->assertModelMissing($this->uploadSlot);
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
