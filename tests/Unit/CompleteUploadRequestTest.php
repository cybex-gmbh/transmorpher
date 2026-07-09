<?php

namespace Tests\Unit;

use App\Classes\Uploader\LocalUploader;
use App\Classes\Uploader\S3Uploader;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Models\Media;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteUploadRequestTest extends TestCase
{
    #[Test]
    public function rulesAreEmptyForLocalUploader(): void
    {
        $this->app->bind('uploader', fn() => new LocalUploader());
        Facade::clearResolvedInstance('uploader');

        $request = new CompleteUploadRequest();
        $request->setContainer($this->app);

        $this->assertEquals([], $request->rules());
    }

    #[Test]
    public function rulesIncludePartsForS3Uploader(): void
    {
        $this->app->bind('uploader', fn() => new S3Uploader());
        Facade::clearResolvedInstance('uploader');

        $request = new CompleteUploadRequest();
        $request->setContainer($this->app);
        $rules = $request->rules();

        $this->assertArrayHasKey('parts', $rules);
        $this->assertArrayHasKey('parts.*.PartNumber', $rules);
        $this->assertArrayHasKey('parts.*.ETag', $rules);
    }

    #[Test]
    public function validateMimeTypePassesForValidMimetypeRule(): void
    {
        Media::validateMimeType('video/mp4', 'mimetypes:video/x-msvideo,video/mpeg,video/mp4');
        $this->assertTrue(true);
    }

    #[Test]
    public function validateMimeTypeThrowsForInvalidMimetypeRule(): void
    {
        $this->expectException(ValidationException::class);
        Media::validateMimeType('application/pdf', 'mimetypes:video/x-msvideo,video/mpeg,video/mp4');
    }

    #[Test]
    public function validateMimeTypePassesForValidMimesRule(): void
    {
        Media::validateMimeType('image/jpeg', 'mimes:jpg,jpeg,png,gif,webp');
        $this->assertTrue(true);
    }

    #[Test]
    public function validateMimeTypeThrowsForInvalidMimesRule(): void
    {
        $this->expectException(ValidationException::class);
        Media::validateMimeType('video/mp4', 'mimes:jpg,jpeg,png,gif,webp');
    }

    #[Test]
    public function validateMimeTypeStripsContentTypeParameters(): void
    {
        // Content-type with charset parameter should still match.
        Media::validateMimeType('image/jpeg; charset=utf-8', 'mimes:jpg,jpeg,png');
        $this->assertTrue(true);
    }

    #[Test]
    public function validateMimeTypeThrowsForUnsupportedRuleFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Media::validateMimeType('image/jpeg', 'required|string');
    }
}



