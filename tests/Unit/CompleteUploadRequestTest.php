<?php

namespace Tests\Unit;

use App\Classes\Upload\DefaultUpload;
use App\Classes\Upload\S3MultipartUpload;
use App\Enums\MediaType;
use App\Http\Requests\V2\CompleteUploadRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteUploadRequestTest extends TestCase
{
    #[Test]
    public function rulesAreEmptyForDefaultUpload(): void
    {
        $this->app->bind('upload', fn() => new DefaultUpload());
        Facade::clearResolvedInstance('upload');

        $request = new CompleteUploadRequest();
        $request->setContainer($this->app);

        $this->assertEquals([], $request->rules());
    }

    #[Test]
    public function rulesIncludePartsForS3MultipartUpload(): void
    {
        Config::set('transmorpher.disks.originals', 's3Originals');
        Config::set('filesystems.disks.s3Originals.bucket', 'test-bucket');

        $this->app->bind('upload', fn() => new S3MultipartUpload());
        Facade::clearResolvedInstance('upload');

        $request = new CompleteUploadRequest();
        $request->setContainer($this->app);
        $rules = $request->rules();

        $this->assertArrayHasKey('parts', $rules);
        $this->assertArrayHasKey('parts.*.PartNumber', $rules);
        $this->assertArrayHasKey('parts.*.ETag', $rules);
    }

    #[Test]
    public function isMimeTypeValidReturnsTrueForValidVideoMimeType(): void
    {
        $this->assertTrue(MediaType::VIDEO->handler()->isMimeTypeValid('video/mp4'));
    }

    #[Test]
    public function isMimeTypeValidReturnsFalseForInvalidVideoMimeType(): void
    {
        $this->assertFalse(MediaType::VIDEO->handler()->isMimeTypeValid('application/pdf'));
    }

    #[Test]
    public function isMimeTypeValidReturnsTrueForValidImageMimeType(): void
    {
        $this->assertTrue(MediaType::IMAGE->handler()->isMimeTypeValid('image/jpeg'));
    }

    #[Test]
    public function isMimeTypeValidReturnsFalseForInvalidImageMimeType(): void
    {
        $this->assertFalse(MediaType::IMAGE->handler()->isMimeTypeValid('video/mp4'));
    }

    #[Test]
    public function isMimeTypeValidReturnsTrueForValidDocumentMimeType(): void
    {
        $this->assertTrue(MediaType::DOCUMENT->handler()->isMimeTypeValid('application/pdf'));
    }

    #[Test]
    public function isMimeTypeValidReturnsFalseForUnknownMimeType(): void
    {
        $this->assertFalse(MediaType::IMAGE->handler()->isMimeTypeValid('application/octet-stream'));
    }
}



