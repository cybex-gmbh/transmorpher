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

abstract class MediaHelper extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalsDisk = Storage::fake($this->originalsStorage->getDiskName());
        Storage::fake(config('chunk-upload.storage.disk'));

        if (isset($this->derivativesStorage)) {
            $this->derivativesDisk = Storage::fake($this->derivativesStorage->getDiskName());
        }

        Sanctum::actingAs(
            $this->user = User::factory()->create(),
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
        return $this->postJson(route('v2.reserveUploadSlot', $this->mediaType), [
            'identifier' => $identifier ?? $this->identifier,
            'filename' => $filename ?? basename($this->mediaFileFilePath),
        ]);
    }

    protected function sendFile(UploadSlot $uploadSlot, ?File $file = null): TestResponse
    {
        return $this->call('PUT', route('v2.upload', $uploadSlot), [
            'identifier' => $uploadSlot->identifier,
        ], [], [
            'file' => $file ?? $this->fakeFile(),
        ]);
    }

    protected function completeUpload(UploadSlot $uploadSlot): TestResponse
    {
        return $this->postJson(route('v2.completeUpload', $uploadSlot));
    }

    protected function abortUpload(UploadSlot $uploadSlot): TestResponse
    {
        return $this->deleteJson(route('v2.abortUpload', $uploadSlot));
    }

    protected function setVersion(Media $media, Version $version): TestResponse
    {
        return $this->patchJson(route('v2.setVersion', [$media, $version]));
    }

    protected function getVersions(Media $media): TestResponse
    {
        return $this->getJson(route('v2.getVersions', $media));
    }

    protected function deleteMedia(Media $media): TestResponse
    {
        return $this->deleteJson(route('v2.delete', $media));
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
}
