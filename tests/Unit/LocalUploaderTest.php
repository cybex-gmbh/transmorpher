<?php

namespace Tests\Unit;

use App\Classes\Uploader\LocalUploader;
use App\Enums\MediaStorage;
use App\Models\UploadSlot;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class LocalUploaderTest extends TestCase
{
    protected LocalUploader $uploader;
    protected UploadSlot $uploadSlot;
    protected string $targetKey;

    protected function createJpegTempFile(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');

        // Minimal JPEG header/footer bytes for finfo mime detection.
        file_put_contents($tempFile, hex2bin('FFD8FFE000104A46494600010100000100010000FFDB004300') . 'jpeg-test' . hex2bin('FFD9'));

        return $tempFile;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');

        $this->uploader = new LocalUploader();
        $this->uploadSlot = new UploadSlot();
        $this->uploadSlot->token = 'test-token-local-123';
        $this->targetKey = 'test-user/test-media/1-test-file.jpg';

        Storage::persistentFake(MediaStorage::ORIGINALS->getDiskName());
    }

    #[Test]
    public function initiateUploadIsNoOp(): void
    {
        // Should not throw.
        $this->uploader->initiateUpload($this->uploadSlot);
        $this->assertTrue(true);
    }

    #[Test]
    public function getChunkUploadUrlReturnsV2UploadRoute(): void
    {
        $url = $this->uploader->getChunkUploadUrl($this->uploadSlot, 1);

        $this->assertStringContainsString($this->uploadSlot->token, $url);
        $this->assertEquals(route('v2.upload', $this->uploadSlot->token), $url);
    }

    #[Test]
    public function completeUploadMovesFileToOriginalsDisk(): void
    {
        $tempFile = $this->createJpegTempFile();

        Cache::put(sprintf('assembled_file_%s', $this->uploadSlot->token), [
            'path' => $tempFile,
            'original_name' => 'test-file.jpg',
            'mime_type' => 'image/jpeg',
        ], now()->addHours(1));

        $result = $this->uploader->completeUpload($this->uploadSlot, [
            'target_key' => $this->targetKey,
            'validation_rules' => 'mimetypes:image/jpeg',
        ]);

        $this->assertNull($result);
        MediaStorage::ORIGINALS->getDisk()->assertExists($this->targetKey);
        $this->assertNull(Cache::get(sprintf('assembled_file_%s', $this->uploadSlot->token)));
    }

    #[Test]
    public function completeUploadThrowsWhenNoAssembledFileInCache(): void
    {
        $this->expectException(RuntimeException::class);

        $this->uploader->completeUpload($this->uploadSlot, []);
    }

    #[Test]
    public function completeUploadClearsAssembledFileFromCache(): void
    {
        $tempFile = $this->createJpegTempFile();

        Cache::put(sprintf('assembled_file_%s', $this->uploadSlot->token), [
            'path' => $tempFile,
            'original_name' => 'test-file.jpg',
            'mime_type' => 'image/jpeg',
        ], now()->addHours(1));

        $this->uploader->completeUpload($this->uploadSlot, [
            'target_key' => $this->targetKey,
            'validation_rules' => 'mimetypes:image/jpeg',
        ]);

        $this->assertNull(Cache::get(sprintf('assembled_file_%s', $this->uploadSlot->token)));
    }

    #[Test]
    public function completeUploadThrowsWhenCompletionMetadataIsMissing(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        Cache::put(sprintf('assembled_file_%s', $this->uploadSlot->token), [
            'path' => $tempFile,
            'original_name' => 'test-file.jpg',
            'mime_type' => 'image/jpeg',
        ], now()->addHours(1));

        $this->expectException(RuntimeException::class);

        $this->uploader->completeUpload($this->uploadSlot, []);
    }

    #[Test]
    public function abortUploadDeletesAssembledFileAndClearsCacheWhenPresent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        Cache::put(sprintf('assembled_file_%s', $this->uploadSlot->token), [
            'path' => $tempFile,
            'original_name' => 'test-file.jpg',
            'mime_type' => 'image/jpeg',
        ], now()->addHours(1));

        $this->uploader->abortUpload($this->uploadSlot);

        $this->assertNull(Cache::get(sprintf('assembled_file_%s', $this->uploadSlot->token)));
        $this->assertFileDoesNotExist($tempFile);
    }

    #[Test]
    public function abortUploadIsNoOpWhenNoAssembledFile(): void
    {
        // Should not throw when no cached file exists.
        $this->uploader->abortUpload($this->uploadSlot);
        $this->assertTrue(true);
    }

    #[Test]
    public function getUploadIdReturnsNull(): void
    {
        $this->assertNull($this->uploader->getUploadId($this->uploadSlot));
    }

    #[Test]
    public function needsUploadIdReturnsFalse(): void
    {
        $this->assertFalse($this->uploader->needsUploadId());
    }

    #[Test]
    public function getCompletionValidationRulesReturnsEmptyArray(): void
    {
        $this->assertEquals([], $this->uploader->getCompletionValidationRules());
    }
}



