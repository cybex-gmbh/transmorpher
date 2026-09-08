<?php

namespace Tests\v2\Support;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class MediaTestBase extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Filesystem $originalsDisk;
    protected Filesystem $derivativesDisk;

    protected MediaStorage $originalsStorage = MediaStorage::ORIGINALS;
    protected MediaStorage $derivativesStorage;
    protected MediaType $mediaType;
    protected ResponseState $versionSetSuccessfulState;
    protected string $identifier;
    protected string $mediaFileFilePath;
    protected string $v2ApiBaseRoute = '/api/v2';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalsDisk = Storage::fake($this->originalsStorage->getDiskName());
        Storage::fake(config('chunk-upload.storage.disk'));

        if (isset($this->derivativesStorage)) {
            $this->derivativesDisk = Storage::fake($this->derivativesStorage->getDiskName());
        }

        Sanctum::actingAs(
            $this->user = User::first() ?? User::factory()->create(),
            ['*']
        );
    }

    protected function fakeFile(?string $filePath = null): File
    {
        $filePath ??= $this->mediaFileFilePath;

        return UploadedFile::fake()->createWithContent(
            basename($filePath),
            file_get_contents(base_path($filePath)),
        );
    }

    protected function reserveUploadSlot(?string $identifier = null, ?string $filename = null): TestResponse
    {
        return $this->postJson($this->reserveUploadSlotRoute($this->mediaType), [
            'identifier' => $identifier ?? $this->identifier,
            'filename' => $filename ?? basename($this->mediaFileFilePath),
        ]);
    }

    protected function sendFile(UploadSlot $uploadSlot, ?File $file = null): TestResponse
    {
        return $this->call('PUT', $this->uploadRoute($uploadSlot->token), [
            'identifier' => $uploadSlot->identifier,
        ], [], [
            'file' => $file ?? $this->fakeFile(),
        ]);
    }

    protected function completeUpload(UploadSlot $uploadSlot): TestResponse
    {
        return $this->postJson($this->completeUploadRoute($uploadSlot->token));
    }

    protected function abortUpload(UploadSlot $uploadSlot): TestResponse
    {
        return $this->deleteJson($this->abortUploadRoute($uploadSlot->token));
    }

    protected function setVersion(Media $media, Version $version): TestResponse
    {
        return $this->patchJson($this->setVersionRoute($media->identifier, $version->number));
    }

    protected function getVersions(Media $media): TestResponse
    {
        return $this->getJson($this->versionsRoute($media->identifier));
    }

    protected function deleteMedia(Media $media): TestResponse
    {
        return $this->deleteJson($this->deleteMediaRoute($media->identifier));
    }

    protected function performUpload(?string $identifier = null, ?File $file = null): Version
    {
        $identifier ??= $this->identifier;
        $file ??= $this->fakeFile();

        $reserveResponse = $this->reserveUploadSlot($identifier, basename($this->mediaFileFilePath));
        $reserveResponse->assertOk();

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));
        $this->assertModelExists($uploadSlot);

        $this->sendFile($uploadSlot, $file)->assertOk();

        $completeResponse = $this->completeUpload($uploadSlot);
        $completeResponse->assertCreated();

        $media = Media::firstWhere('identifier', $identifier);
        $this->assertModelExists($media);

        $version = $media->Versions()->whereNumber($completeResponse->json('version'))->first();
        $this->assertModelExists($version);

        return $version;
    }

    protected function reserveUploadSlotRoute(MediaType $mediaType): string
    {
        return sprintf('%s/%s/reserveUploadSlot', $this->v2ApiBaseRoute, $mediaType->value);
    }

    protected function uploadRoute(string $uploadToken): string
    {
        return sprintf('%s/upload/%s', $this->v2ApiBaseRoute, $uploadToken);
    }

    protected function completeUploadRoute(string $uploadToken): string
    {
        return sprintf('%s/complete', $this->uploadRoute($uploadToken));
    }

    protected function abortUploadRoute(string $uploadToken): string
    {
        return $this->uploadRoute($uploadToken);
    }

    protected function versionsRoute(string $mediaIdentifier): string
    {
        return sprintf('%s/media/%s/versions', $this->v2ApiBaseRoute, $mediaIdentifier);
    }

    protected function deleteMediaRoute(string $mediaIdentifier): string
    {
        return sprintf('%s/media/%s', $this->v2ApiBaseRoute, $mediaIdentifier);
    }

    protected function setVersionRoute(string $mediaIdentifier, int $versionNumber): string
    {
        return sprintf('%s/media/%s/version/%d', $this->v2ApiBaseRoute, $mediaIdentifier, $versionNumber);
    }
}
