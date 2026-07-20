<?php

namespace Tests\v2\Feature\Media;

use App\Enums\ResponseState;
use App\Enums\UploadState;
use App\Models\UploadSlot;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\v2\Support\MediaHelper;

abstract class MediaTestCase extends MediaHelper
{
    #[Test]
    public function canListVersions(): void
    {
        $version = $this->performUpload();

        $response = $this->getVersions($version->Media);

        $response->assertOk();
        $response->assertJsonFragment([
            'state' => UploadState::SUCCESS->value,
            'identifier' => $this->identifier,
        ]);
        $this->assertArrayHasKey((string)$version->number, $response->json('versions'));
    }

    #[Test]
    public function invalidatesUploadTokenAfterCompletion(): void
    {
        $reserveResponse = $this->reserveUploadSlot();
        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));

        $this->assertModelExists($uploadSlot);

        $this->sendFile($uploadSlot)->assertOk();
        $this->completeUpload($uploadSlot)->assertCreated();

        $this->completeUpload($uploadSlot)->assertNotFound();
    }

    #[Test]
    public function canAbortUpload(): void
    {
        $reserveResponse = $this->reserveUploadSlot();
        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));

        $this->assertModelExists($uploadSlot);

        $abortResponse = $this->abortUpload($uploadSlot);

        $abortResponse->assertOk();
        $abortResponse->assertJsonFragment([
            'state' => ResponseState::UPLOAD_ABORTED->getState()->value,
            'message' => ResponseState::UPLOAD_ABORTED->getMessage(),
            'identifier' => $uploadSlot->identifier,
        ]);

        $uploadSlot->refresh();
        $this->assertFalse((bool) $uploadSlot->is_valid);

        $this->completeUpload($uploadSlot)->assertNotFound();
    }

    #[Test]
    public function deletesTemporaryFilesAfterAbortingUpload(): void
    {
        $reserveResponse = $this->reserveUploadSlot();
        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));

        $this->assertModelExists($uploadSlot);
        $this->sendFile($uploadSlot)->assertOk();

        $chunkDisk = Storage::disk((string) config('chunk-upload.storage.disk'));
        $temporaryFilesBeforeAbort = $chunkDisk->allFiles();

        $this->assertNotEmpty($temporaryFilesBeforeAbort);

        $this->abortUpload($uploadSlot)->assertOk();

        foreach ($temporaryFilesBeforeAbort as $temporaryFile) {
            $chunkDisk->assertMissing($temporaryFile);
        }
    }
}
