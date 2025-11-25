<?php

namespace Tests\Unit;

use App\Console\Commands\PurgeDerivatives;
use App\Enums\ClientNotification;
use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Helpers\SodiumHelper;
use App\Models\Media;
use App\Models\Version;
use Artisan;
use Config;
use File;
use Http;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser;
use Storage;
use Tests\OnDemandDerivativeMediaTest;

class PdfTest extends OnDemandDerivativeMediaTest
{
    protected string $identifier = 'testPdf';
    protected string $mediaName = 'document.pdf';
    protected ResponseState $versionSetSuccessful = ResponseState::DOCUMENT_VERSION_SET;
    protected MediaType $mediaType = MediaType::DOCUMENT;

    protected function setUp(): void
    {
        parent::setUp();

        $this->derivativesDisk ??= Storage::persistentFake(MediaStorage::DOCUMENT_DERIVATIVES->getDiskName());
        $this->mediaFile = UploadedFile::fake()->createWithContent($this->mediaName, File::get(base_path('tests/data/test.pdf')));

        Config::set('transmorpher.document_remove_metadata', true);
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
    #[Depends('ensureMediaCanBeUploaded')]
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
    public function ensurePdfDerivativeImagesCanBeDownloaded(string $transformations, int $expectedStatusCode, string $expectedContentType, Version $version)
    {
        $response = $this->getDerivative($version, $transformations);

        if ($expectedContentType === 'image/jpg') {
            // Our configuration options and the enum expect 'jpg', but the Content-Type header uses 'jpeg'.
            $expectedContentType = 'image/jpeg';
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
                'application/pdf',
            ],
            'height' => [
                'h-100',
                200,
                'application/pdf',
            ],
            'width and height' => [
                'w-100+h-100',
                200,
                'application/pdf',
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
                'application/pdf',
            ],
            'page width height format png' => [
                'p-1+f-png+w-500+h-1000',
                200,
                'image/png',
            ],
            'ppi' => [
                'ppi-100',
                200,
                'application/pdf',
            ]
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
    #[Depends('ensureVersionCanBeSet')]
    public function ensurePdfMetadataIsRemoved(Version $version)
    {
        $config = new PdfParserConfig();
        $config->setRetainImageContent(false);
        $pdfParser = new Parser([], $config);

        $originalMetadata = $pdfParser->parseFile($this->originalsDisk->path($version->originalFilePath()))->getDetails();
        $derivativeMetadata = $pdfParser->parseFile($this->derivativesDisk->path($version->onDemandDerivativeFilePath()))->getDetails();

        $metadataExpectationArray = $this->getMetadataExpectationArray();

        foreach ($metadataExpectationArray as $key => $expected) {
            $this->assertArrayHasKey($key, $originalMetadata);

            if ($expected['isPresent']) {
                $this->assertNotEquals($derivativeMetadata[$key], $originalMetadata[$key]);
                $this->assertMatchesRegularExpression($expected['regex'], $derivativeMetadata[$key]);
            } else {
                $this->assertArrayNotHasKey($key, $derivativeMetadata);
            }
        }
    }

    #[Test]
    #[Depends('ensurePdfMetadataIsRemoved')]
    public function ensurePdfMetadataIsKept()
    {
        Config::set('transmorpher.document_remove_metadata', false);

        $reserveUploadSlotResponse = $this->reserveUploadSlot();
        $uploadResponse = $this->uploadMedia($reserveUploadSlotResponse->json('upload_token'));
        $media = Media::whereIdentifier($this->identifier)->first();
        $version = $media->Versions()->whereNumber($uploadResponse['version'])->first();
        $this->getDerivative($version);

        $config = new PdfParserConfig();
        $config->setRetainImageContent(false);
        $pdfParser = new Parser([], $config);

        $originalMetadata = $pdfParser->parseFile($this->originalsDisk->path($version->originalFilePath()))->getDetails();
        $derivativeMetadata = $pdfParser->parseFile($this->derivativesDisk->path($version->onDemandDerivativeFilePath()))->getDetails();

        $this->assertEquals($originalMetadata, $derivativeMetadata);
    }

    /**
     * @return array
     */
    protected function getMetadataExpectationArray(): array
    {
        return [
            'Creator' => [
                'isPresent' => false,
            ],
            'ModDate' => [
                'isPresent' => true,
                'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'
            ],
            'CreationDate' => [
                'isPresent' => true,
                'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'
            ],
            'Producer' => [
                'isPresent' => true,
                'regex' => '/^TCPDF/'
            ],
            'Subject' => [
                'isPresent' => false,
            ],
            'CustomMetadata' => [
                'isPresent' => false,
            ],
            'Author' => [
                'isPresent' => false,
            ],
            'Title' => [
                'isPresent' => false,
            ],
            "xmp:createdate" => [
                'isPresent' => true,
                'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'
            ],
            "xmp:creatortool" => [
                'isPresent' => false,
            ],
            "xmp:modifydate" => [
                'isPresent' => true,
                'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'
            ],
            "xmp:metadatadate" => [
                'isPresent' => true,
                'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'
            ],
            "dc:description" => [
                'isPresent' => true,
                'regex' => '//'
            ],
            "dc:title" => [
                'isPresent' => true,
                'regex' => '//'
            ],
            "dc:creator" => [
                'isPresent' => true,
                'regex' => '//'
            ],
            "pdf:producer" => [
                'isPresent' => true,
                'regex' => '/^TCPDF/'
            ],
            "xmpmm:documentid" => [
                'isPresent' => true,
                'regex' => '/^uuid:/'
            ],
            "xmpmm:instanceid" => [
                'isPresent' => true,
                'regex' => '/^uuid:/'
            ],
        ];
    }

    #[Test]
    #[Depends('ensureVersionCanBeSet')]
    public function ensurePdfDerivativesArePurged(Version $version)
    {
        $this->derivativesDisk->assertExists($version->onDemandDerivativeFilePath());

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
        $this->derivativesDisk->assertMissing($version->onDemandDerivativeFilePath());
    }
}
