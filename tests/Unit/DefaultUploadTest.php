<?php

namespace Tests\Unit;

use App\Classes\Upload\DefaultUpload;
use App\Enums\MediaStorage;
use App\Models\UploadSlot;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DefaultUploadTest extends TestCase
{
    protected DefaultUpload $upload;
    protected UploadSlot $uploadSlot;
    protected string $targetKey;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');

        $this->upload = new DefaultUpload();
        $this->uploadSlot = new UploadSlot();
        $this->uploadSlot->token = 'test-token-local-' . uniqid();
        $this->uploadSlot->identifier = 'local-uploader-test-' . uniqid();
        $this->uploadSlot->filename = 'test-file.jpg';
        $this->uploadSlot->setRelation('User', User::factory()->make(['name' => 'local-user']));
        $this->targetKey = $this->uploadSlot->originalFilePath;

        Storage::persistentFake(MediaStorage::ORIGINALS->getDiskName());
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

    #[Test]
    public function completeUploadValidatesStoredFile(): void
    {
        MediaStorage::ORIGINALS->getDisk()->put($this->targetKey, 'test-image-content');

        $this->upload->complete($this->uploadSlot, [
            'validation_rules' => 'mimetypes:text/plain,image/jpeg',
        ]);

        MediaStorage::ORIGINALS->getDisk()->assertExists($this->targetKey);
    }

    #[Test]
    public function completeUploadThrowsWhenNoStoredFileExists(): void
    {
        $this->expectException(RuntimeException::class);

        $this->upload->complete($this->uploadSlot, [
            'validation_rules' => 'mimetypes:text/plain',
        ]);
    }

    #[Test]
    public function completeUploadDeletesStoredFileWhenMimeValidationFails(): void
    {
        MediaStorage::ORIGINALS->getDisk()->put($this->targetKey, 'plain text content');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->upload->complete($this->uploadSlot, [
            'validation_rules' => 'mimetypes:application/pdf',
        ]);
    }

    #[Test]
    public function completeUploadValidationFailureRemovesStoredFile(): void
    {
        MediaStorage::ORIGINALS->getDisk()->put($this->targetKey, 'plain text content');

        try {
            $this->upload->complete($this->uploadSlot, [
                'validation_rules' => 'mimetypes:application/pdf',
            ]);
        } catch (\Throwable) {
            // Assertion is below.
        }

        MediaStorage::ORIGINALS->getDisk()->assertMissing($this->targetKey);
    }

    #[Test]
    public function completeUploadThrowsWhenCompletionMetadataIsMissing(): void
    {
        MediaStorage::ORIGINALS->getDisk()->put($this->targetKey, 'plain text content');

        $this->expectException(RuntimeException::class);

        $this->upload->complete($this->uploadSlot, []);
    }

    #[Test]
    public function abortUploadDeletesStoredFileWhenPresent(): void
    {
        MediaStorage::ORIGINALS->getDisk()->put($this->targetKey, 'plain text content');

        $this->upload->abort($this->uploadSlot);

        MediaStorage::ORIGINALS->getDisk()->assertMissing($this->targetKey);
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




