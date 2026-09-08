<?php

namespace Tests\v2\Feature\Media\OnDemand\Document;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser;
use Tests\v2\Feature\Media\OnDemand\OnDemandMediaTestCase;

class DocumentTest extends OnDemandMediaTestCase
{
    protected MediaType $mediaType = MediaType::DOCUMENT;
    protected MediaStorage $derivativesStorage = MediaStorage::DOCUMENT_DERIVATIVES;
    protected ResponseState $versionSetSuccessfulState = ResponseState::DOCUMENT_VERSION_SET;
    protected string $identifier = 'test-document';
    protected string $mediaFileFilePath = 'tests/data/test.pdf';
    protected string $originalContentType = 'application/pdf';
    protected string $derivativeContentType = 'application/pdf';

    #[Test]
    #[DataProvider('providePdfImageDerivativeTransformations')]
    public function canDownloadPdfImageDerivativesFromPublicEndpoint(string $transformations, string $expectedContentType): void
    {
        $version = $this->performUpload();

        $response = $this->getPublicDerivative($version, $transformations);

        $response->assertOk();
        $response->assertHeader('Content-Type', $expectedContentType);
    }

    #[Test]
    public function removesMetadataIfConfigured(): void
    {
        Config::set('transmorpher.document_remove_metadata', true);

        $version = $this->performUpload();
        $this->getPublicDerivative($version)->assertOk();

        $originalMetadata = $this->parsePdfMetadata($this->originalsDisk->path($version->originalFilePath()));
        $derivativeMetadata = $this->parsePdfMetadata($this->derivativesDisk->path($version->onDemandDerivativeFilePath()));

        foreach ($this->getMetadataExpectationArray() as $key => $expected) {
            $this->assertArrayHasKey($key, $originalMetadata);

            if ($expected['isPresent']) {
                $this->assertArrayHasKey($key, $derivativeMetadata);
                $this->assertNotEquals($originalMetadata[$key], $derivativeMetadata[$key]);
                $this->assertMatchesRegularExpression($expected['regex'], $derivativeMetadata[$key]);

                continue;
            }

            $this->assertArrayNotHasKey($key, $derivativeMetadata);
        }
    }

    #[Test]
    public function keepsMetadataIfConfigured(): void
    {
        Config::set('transmorpher.document_remove_metadata', false);

        $version = $this->performUpload();
        $this->getPublicDerivative($version)->assertOk();

        $originalMetadata = $this->parsePdfMetadata($this->originalsDisk->path($version->originalFilePath()));
        $derivativeMetadata = $this->parsePdfMetadata($this->derivativesDisk->path($version->onDemandDerivativeFilePath()));

        $this->assertEquals($originalMetadata, $derivativeMetadata);
    }

    public static function providePdfImageDerivativeTransformations(): array
    {
        // PPI is added to formats to make tests way faster.
        return [
            'width' => ['w-100', 'application/pdf'],
            'height' => ['h-100', 'application/pdf'],
            'width and height' => ['w-100+h-100', 'application/pdf'],
            'format png' => ['f-png+ppi-2', 'image/png'],
            'format webp' => ['f-webp+ppi-2', 'image/webp'],
            'format jpg' => ['f-jpg+ppi-2', 'image/jpeg'],
            'format gif' => ['f-gif+ppi-2', 'image/gif'],
            'page' => ['p-1', 'application/pdf'],
            'page width height format png' => ['p-1+f-png+w-500+h-1000', 'image/png'],
            'ppi' => ['ppi-100', 'application/pdf']
        ];
    }

    protected function getMetadataExpectationArray(): array
    {
        return [
            'Creator' => ['isPresent' => false],
            'ModDate' => ['isPresent' => true, 'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'],
            'CreationDate' => ['isPresent' => true, 'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'],
            'Producer' => ['isPresent' => true, 'regex' => '/^TCPDF/'],
            'Subject' => ['isPresent' => false],
            'CustomMetadata' => ['isPresent' => false],
            'Author' => ['isPresent' => false],
            'Title' => ['isPresent' => false],
            'xmp:createdate' => ['isPresent' => true, 'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'],
            'xmp:creatortool' => ['isPresent' => false],
            'xmp:modifydate' => ['isPresent' => true, 'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'],
            'xmp:metadatadate' => ['isPresent' => true, 'regex' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/'],
            'dc:description' => ['isPresent' => true, 'regex' => '//'],
            'dc:title' => ['isPresent' => true, 'regex' => '//'],
            'dc:creator' => ['isPresent' => true, 'regex' => '//'],
            'pdf:producer' => ['isPresent' => true, 'regex' => '/^TCPDF/'],
            'xmpmm:documentid' => ['isPresent' => true, 'regex' => '/^uuid:/'],
            'xmpmm:instanceid' => ['isPresent' => true, 'regex' => '/^uuid:/'],
        ];
    }

    protected function parsePdfMetadata(string $filePath): array
    {
        $config = new PdfParserConfig();
        $config->setRetainImageContent(false);
        $pdfParser = new Parser([], $config);

        return $pdfParser->parseFile($filePath)->getDetails();
    }
}

