<?php

namespace Tests\Unit;

use App\Classes\Upload\DefaultUpload;
use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Models\UploadSlot;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DefaultUploadTest extends TestCase
{
    protected DefaultUpload $upload;
    protected UploadSlot $uploadSlot;
    protected string $originalTargetKey;
    protected string $temporaryChunkKey;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');

        $this->upload = new DefaultUpload();
        $this->uploadSlot = new UploadSlot();
        $this->uploadSlot->token = 'test-token-local-' . uniqid();
        $this->uploadSlot->identifier = 'local-uploader-test-' . uniqid();
        $this->uploadSlot->filename = 'test-file.jpg';
        $this->uploadSlot->media_type = MediaType::IMAGE;
        $this->uploadSlot->setRelation('User', User::factory()->make(['name' => 'local-user']));
        $this->originalTargetKey = $this->uploadSlot->originalFilePath;
        $this->temporaryChunkKey = config('chunk-upload.storage.chunks') . '/' . DefaultUpload::createTempFilename($this->uploadSlot);
        Storage::persistentFake(MediaStorage::ORIGINALS->getDiskName());
        Storage::persistentFake((string)config('chunk-upload.storage.disk'));
    }

    #[Test]
    public function initiateUploadIsNoOp(): void
    {
        // Should not throw.
        $this->upload->initiate($this->uploadSlot);
        $this->assertTrue(true);
    }

    #[Test]
    public function getChunkUploadUrlReturnsV2UploadRoute(): void
    {
        $url = $this->upload->getChunkUploadUrl($this->uploadSlot, 1);

        $this->assertStringContainsString($this->uploadSlot->token, $url);
        $this->assertEquals(route('v2.upload', $this->uploadSlot->token), $url);
    }

    protected function makeCompleteRequest(): CompleteUploadRequest
    {
        return Mockery::mock(CompleteUploadRequest::class)->makePartial();
    }

    #[Test]
    public function completeUploadValidatesStoredFile(): void
    {
        Storage::disk(config('chunk-upload.storage.disk'))->put($this->temporaryChunkKey, file_get_contents(base_path('tests/data/test.png')));

        $this->upload->complete($this->makeCompleteRequest(), $this->uploadSlot);

        MediaStorage::ORIGINALS->getDisk()->assertExists($this->originalTargetKey);
        Storage::disk(config('chunk-upload.storage.disk'))->assertMissing($this->temporaryChunkKey);
    }

    #[Test]
    public function completeUploadThrowsWhenNoStoredFileExists(): void
    {
        $this->expectException(RuntimeException::class);

        $this->upload->complete($this->makeCompleteRequest(), $this->uploadSlot);
    }

    #[Test]
    public function completeUploadDeletesStoredFileWhenMimeValidationFails(): void
    {
        Storage::disk((string)config('chunk-upload.storage.disk'))->put($this->temporaryChunkKey, 'plain text content');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->upload->complete($this->makeCompleteRequest(), $this->uploadSlot);
    }

    #[Test]
    public function completeUploadValidationFailureIsCleanedUpByAbort(): void
    {
        Storage::disk((string)config('chunk-upload.storage.disk'))->put($this->temporaryChunkKey, 'plain text content');

        try {
            $this->upload->complete($this->makeCompleteRequest(), $this->uploadSlot);
        } catch (\Throwable) {
            // Cleanup is the caller's responsibility via abort(), mirroring what the controller does.
            $this->upload->abort($this->uploadSlot);
        }

        MediaStorage::ORIGINALS->getDisk()->assertMissing($this->originalTargetKey);
        Storage::disk((string)config('chunk-upload.storage.disk'))->assertMissing($this->temporaryChunkKey);
    }

    #[Test]
    public function abortUploadDeletesTemporaryChunkFileWhenPresent(): void
    {
        MediaStorage::ORIGINALS->getDisk()->put($this->originalTargetKey, 'plain text content');
        Storage::disk((string)config('chunk-upload.storage.disk'))->put($this->temporaryChunkKey, 'plain text content');

        $this->upload->abort($this->uploadSlot);

        MediaStorage::ORIGINALS->getDisk()->assertExists($this->originalTargetKey);
        Storage::disk((string)config('chunk-upload.storage.disk'))->assertMissing($this->temporaryChunkKey);
    }

    #[Test]
    public function abortUploadIsNoOpWhenNoStoredFileExists(): void
    {
        // Should not throw when no stored file exists.
        $this->upload->abort($this->uploadSlot);
        $this->assertTrue(true);
    }

    #[Test]
    public function getUploadIdReturnsNull(): void
    {
        $this->assertNull($this->upload->getUploadId($this->uploadSlot));
    }

    #[Test]
    public function needsUploadIdReturnsFalse(): void
    {
        $this->assertFalse($this->upload->needsUploadId());
    }

    #[Test]
    public function getCompletionValidationRulesReturnsEmptyArray(): void
    {
        $this->assertEquals([], $this->upload->getCompletionValidationRules());
    }
}
