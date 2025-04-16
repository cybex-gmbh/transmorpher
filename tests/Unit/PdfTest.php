<?php

use App\Console\Commands\PurgeDerivatives;
use App\Enums\ClientNotification;
use App\Enums\MediaStorage;
use App\Helpers\SodiumHelper;
use App\Models\Media;
use App\Models\Version;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser;
use Tests\MediaTest;

class PdfTest extends MediaTest
{
    protected const IDENTIFIER = 'testPdf';
    protected const PDF_NAME = 'document.pdf';
    protected Filesystem $pdfDerivativesDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdfDerivativesDisk ??= Storage::persistentFake(config(sprintf('transmorpher.disks.%s', MediaStorage::DOCUMENT_DERIVATIVES->value)));
    }

    protected function reserveUploadSlot(): TestResponse
    {
        return $this->json('POST', route('v1.reserveDocumentUploadSlot'), [
            'identifier' => self::IDENTIFIER
        ]);
    }

    #[Test]
    public function ensurePdfUploadSlotCanBeReserved()
    {
        $reserveUploadSlotResponse = $this->reserveUploadSlot();

        $reserveUploadSlotResponse->assertOk();

        return $reserveUploadSlotResponse->json()['upload_token'];
    }

    protected function uploadPdf(string $uploadToken): TestResponse
    {
        return $this->json('POST', route('v1.upload', [$uploadToken]), [
            'file' => UploadedFile::fake()->createWithContent(self::PDF_NAME, File::get(base_path('tests/data/test.pdf'))),
            'identifier' => self::IDENTIFIER
        ]);
    }

    #[Test]
    #[Depends('ensurePdfUploadSlotCanBeReserved')]
    public function ensurePdfCanBeUploaded(string $uploadToken)
    {
        $uploadResponse = $this->uploadPdf($uploadToken);

        $uploadResponse->assertCreated();

        $media = Media::whereIdentifier(self::IDENTIFIER)->first();
        $version = $media->Versions()->whereNumber($uploadResponse['version'])->first();

        Storage::disk(config('transmorpher.disks.originals'))->assertExists($version->originalFilePath());

        return $version;
    }

    #[Test]
    #[Depends('ensurePdfUploadSlotCanBeReserved')]
    #[Depends('ensurePdfCanBeUploaded')]
    public function ensureUploadTokenIsInvalidatedAfterUpload(string $uploadToken)
    {
        $this->uploadPdf($uploadToken)->assertNotFound();
    }

    protected function getOriginal(Version $version): TestResponse
    {
        return $this->get(route('v1.getDocumentOriginal', [$version->Media, $version]));
    }

    protected function getDerivative(Version $version, ?string $transformations = null): TestResponse
    {
        return $this->get(route('getDocumentDerivative', [$this->user->name, $version->Media, $transformations]));
    }

    #[Test]
    #[Depends('ensurePdfCanBeUploaded')]
    public function ensurePdfOriginalCanBeDownloaded(Version $version)
    {
        $response = $this->getOriginal($version);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        return $version;
    }

    /**
     * This test is not part of the data provider below, so it can be depended on in other tests.
     * It covers the case where no transformations are applied.
     */
    #[Test]
    #[Depends('ensurePdfOriginalCanBeDownloaded')]
    public function ensurePdfDerivativeCanBeDownloaded(Version $version)
    {
        $response = $this->getDerivative($version);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        return $version;
    }

    #[Test]
    #[Depends('ensurePdfDerivativeCanBeDownloaded')]
    #[DataProvider('providePdfTransformationStrings')]
    public function ensurePdfDerivativeImagesCanBeDownloaded(string $transformations, int $expectedStatusCode, callable|string $expectedContentType, Version $version)
    {
        $response = $this->getDerivative($version, $transformations);

        if (is_callable($expectedContentType)) {
            // The requested image format is taken from the config, where the default is configured.
            $expectedContentType = $expectedContentType();

            if ($expectedContentType === 'image/jpg') {
                // Our configuration options and the enum expect 'jpg', but the Content-Type header uses 'jpeg'.
                $expectedContentType = 'image/jpeg';
            }
        }

        $response->assertStatus($expectedStatusCode);
        $response->assertHeader('Content-Type', $expectedContentType);
    }

    public static function providePdfTransformationStrings(): array
    {
        return [
            'width' => [
                'w-100',
                200,
                fn() => sprintf('image/%s', config('transmorpher.document_default_image_format')),
            ],
            'height' => [
                'h-100',
                200,
                fn() => sprintf('image/%s', config('transmorpher.document_default_image_format')),
            ],
            'width and height' => [
                'w-100+h-100',
                200,
                fn() => sprintf('image/%s', config('transmorpher.document_default_image_format')),
            ],
            'format png' => [
                'f-png',
                200,
                'image/png',
            ],
            'format webp' => [
                'f-webp',
                200,
                'image/webp',
            ],
            'format jpg' => [
                'f-jpg',
                200,
                'image/jpeg',
            ],
            'format gif' => [
                'f-gif',
                200,
                'image/gif',
            ],
            'page' => [
                'p-1',
                200,
                fn() => sprintf('image/%s', config('transmorpher.document_default_image_format')),
            ],
            'page width height format png' => [
                'p-1+f-png+w-500+h-1000',
                200,
                'image/png',
            ],
        ];
    }

    #[Test]
    #[Depends('ensurePdfDerivativeCanBeDownloaded')]
    #[Depends('ensurePdfDerivativeImagesCanBeDownloaded')]
    public function ensureUnprocessedFilesAreNotAvailable(Version $version)
    {
        $version->Media->Versions->each->update(['processed' => 0]);

        $this->get(route('getDocumentDerivative', [$this->user->name, $version->Media]))->assertNotFound();

        return $version;
    }

    #[Test]
    #[Depends('ensurePdfDerivativeCanBeDownloaded')]
    public function ensurePdfMetadataIsRemoved(Version $version)
    {
        $config = new PdfParserConfig();
        $config->setRetainImageContent(false);
        $pdfParser = new Parser([], $config);

        // Empty values are filtered for easier comparison.
        $originalMetadata = array_filter($pdfParser->parseFile($this->originalsDisk->path($version->originalFilePath()))->getDetails());
        $derivativeMetadata = array_filter($pdfParser->parseFile($this->pdfDerivativesDisk->path($version->nonVideoDerivativeFilePath()))->getDetails());

        $metadataComparisonKeys = [
            'Creator',
            'ModDate',
            'CreationDate',
            'Producer',
            'Subject',
            'CustomMetadata',
            'Author',
            'Title',
            "xmp:createdate",
            "xmp:creatortool",
            "xmp:modifydate",
            "xmp:metadatadate",
            "dc:description",
            "dc:title",
            "dc:creator",
            "pdf:producer",
            "xmpmm:documentid",
            "xmpmm:instanceid",
        ];

        foreach ($metadataComparisonKeys as $key) {
            array_key_exists($key, $originalMetadata)
            && array_key_exists($key, $derivativeMetadata)
            && $this->assertNotEquals($originalMetadata[$key], $derivativeMetadata[$key]);
        }
    }

    #[Test]
    #[Depends('ensurePdfDerivativeCanBeDownloaded')]
    public function ensurePdfDerivativesArePurged(Version $version)
    {
        $this->pdfDerivativesDisk->assertExists($version->nonVideoDerivativeFilePath());

        $cacheCounterBeforeCommand = $this->originalsDisk->get(config('transmorpher.cache_invalidation_counter_file_path'));

        Http::fake([
            $this->user->api_url => Http::response()
        ]);

        Artisan::call(PurgeDerivatives::class, ['--document' => true]);

        $cacheCounterAfterCommand = $this->originalsDisk->get(config('transmorpher.cache_invalidation_counter_file_path'));

        Http::assertSent(function (Request $request) use ($cacheCounterAfterCommand) {
            $decryptedNotification = json_decode(SodiumHelper::decrypt($request['signed_notification']), true);

            return $request->url() == $this->user->api_url
                && $decryptedNotification['notification_type'] == ClientNotification::CACHE_INVALIDATION->value
                && $decryptedNotification['cache_invalidator'] == $cacheCounterAfterCommand;
        });

        $this->assertTrue(++$cacheCounterBeforeCommand == $cacheCounterAfterCommand);
        $this->pdfDerivativesDisk->assertMissing($version->nonVideoDerivativeFilePath());
    }
}
