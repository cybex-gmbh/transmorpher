<?php

namespace Tests\Unit;

use App\Console\Commands\PurgeDerivatives;
use App\Enums\ClientNotification;
use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Exceptions\InvalidTransformationFormatException;
use App\Exceptions\InvalidTransformationValueException;
use App\Exceptions\TransformationNotFoundException;
use App\Helpers\SodiumHelper;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\Version;
use Artisan;
use Http;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\Request;
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

        Storage::disk(config('transmorpher.disks.originals'))->assertExists($version->originalFilePath());

        return $version;
    }

    #[Test]
    #[Depends('ensureImageUploadSlotCanBeReserved')]
    #[Depends('ensureImageCanBeUploaded')]
    public function ensureUploadTokenIsInvalidatedAfterUpload(string $uploadToken)
    {
        $this->uploadImage($uploadToken)->assertNotFound();
    }

    protected function createDerivativeForVersion(Version $version): TestResponse
    {
        return $this->get(route('getDerivative', [$this->user->name, $version->Media]));
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
        $getDerivativeResponse = $this->get(route('getDerivative', [$this->user->name, $version->Media]));

        $getDerivativeResponse->assertNotFound();
    }

    protected function assertVersionFilesExist(Version $version): void
    {
        $this->originalsDisk->assertExists($version->originalFilePath());
        $this->imageDerivativesDisk->assertExists($version->imageDerivativeFilePath());
    }

    protected function assertMediaDirectoryExists(Media $media): void
    {
        $this->originalsDisk->assertExists($media->baseDirectory());
    }

    protected function assertUserDirectoryExists(): void
    {
        $this->originalsDisk->assertExists($this->user->name);
        $this->imageDerivativesDisk->assertExists($this->user->name);
    }

    protected function assertVersionFilesMissing(Version $version): void
    {
        $this->originalsDisk->assertMissing($version->originalFilePath());
        $this->imageDerivativesDisk->assertMissing($version->imageDerivativeFilePath());
    }

    protected function assertMediaDirectoryMissing(Media $media): void
    {
        $this->originalsDisk->assertMissing($media->baseDirectory());
        $this->imageDerivativesDisk->assertMissing($media->baseDirectory());
    }

    protected function assertUserDirectoryMissing(): void
    {
        $this->originalsDisk->assertMissing($this->user->name);
        $this->imageDerivativesDisk->assertMissing($this->user->name);
    }

    protected function setupMediaAndVersion(): void
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
        $this->setupMediaAndVersion();

        $this->assertVersionFilesExist($this->version);

        $this->runProtectedMethod($this->version, 'deleteFiles');

        $this->assertVersionFilesMissing($this->version);
    }

    #[Test]
    public function ensureMediaDeletionMethodsWork()
    {
        $this->setupMediaAndVersion();

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
    public function ensureImageDerivativesArePurged()
    {
        $this->setupMediaAndVersion();

        $this->assertVersionFilesExist($this->version);

        $cacheCounterBeforeCommand = $this->originalsDisk->get(config('transmorpher.cache_invalidation_counter_file_path'));

        Http::fake([
            $this->user->api_url => Http::response()
        ]);

        Artisan::call(PurgeDerivatives::class, ['--image' => true]);

        $cacheCounterAfterCommand = $this->originalsDisk->get(config('transmorpher.cache_invalidation_counter_file_path'));

        Http::assertSent(function (Request $request) use ($cacheCounterAfterCommand) {
            $decryptedNotification = json_decode(SodiumHelper::decrypt($request['signed_notification']), true);

            return $request->url() == $this->user->api_url
                && $decryptedNotification['notification_type'] == ClientNotification::CACHE_INVALIDATION->value
                && $decryptedNotification['cache_invalidator'] == $cacheCounterAfterCommand;
        });

        $this->assertTrue(++$cacheCounterBeforeCommand == $cacheCounterAfterCommand);
        $this->imageDerivativesDisk->assertMissing($this->version->imageDerivativeFilePath());
    }

    #[Test]
    public function ensureUserDeletionMethodsWork()
    {
        $this->setupMediaAndVersion();

        $this->assertVersionFilesExist($this->version);
        $this->assertMediaDirectoryExists($this->media);
        $this->assertUserDirectoryExists();

        $this->runProtectedMethod($this->user, 'deleteRelatedModels');
        $this->runProtectedMethod($this->user, 'deleteMediaDirectories');

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
