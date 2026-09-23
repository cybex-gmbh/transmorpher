<?php

namespace Tests\v2\Feature\Media\Video;

use App\Console\Commands\PurgeDerivatives;
use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Jobs\ClientPurgeNotification;
use App\Models\Media;
use App\Models\UploadSlot;
use Artisan;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\v2\Feature\Media\MediaTestCase;
use Transcode;

class VideoTest extends MediaTestCase
{
    protected MediaType $mediaType = MediaType::VIDEO;
    protected MediaStorage $derivativesStorage = MediaStorage::VIDEO_DERIVATIVES;
    protected ResponseState $uploadSuccessfulState = ResponseState::VIDEO_UPLOAD_SUCCESSFUL;
    protected ResponseState $versionSetSuccessfulState = ResponseState::VIDEO_VERSION_SET;
    protected string $identifier = 'test-video';
    protected string $mediaFileFilePath = 'tests/data/test.mp4';

    #[Test]
    public function canUploadVideo(): void
    {
        Queue::fake();

        $reserveResponse = $this->reserveUploadSlot();
        $reserveResponse->assertOk();

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));
        $this->assertModelExists($uploadSlot);

        $this->sendFile($uploadSlot)->assertOk();

        $completeResponse = $this->completeUpload($uploadSlot);
        Queue::assertPushed(Transcode::getJobClass());
        $completeResponse->assertCreated();
        $completeResponse->assertJsonFragment([
            'state' => $this->uploadSuccessfulState->getState()->value,
            'message' => $this->uploadSuccessfulState->getMessage(),
        ]);

        $media = Media::firstWhere('identifier', $this->identifier);
        $this->assertModelExists($media);

        $version = $media->Versions()->whereNumber($completeResponse->json('version'))->first();
        $this->assertModelExists($version);
        $this->assertFalse((bool)$version->processed);
        $this->originalsDisk->assertExists($version->originalFilePath());
    }

    #[Test]
    public function canListVersions(): void
    {
        Queue::fake();

        parent::canListVersions();
    }

    #[Test]
    public function invalidatesUploadTokenAfterCompletion(): void
    {
        Queue::fake();

        parent::invalidatesUploadTokenAfterCompletion();
    }

    #[Test]
    public function canSetVersion(): void
    {
        Queue::fake();

        $originalVersion = $this->performUpload();
        Queue::assertPushed(Transcode::getJobClass());

        $response = $this->setVersion($originalVersion->Media, $originalVersion);
        Queue::assertPushed(Transcode::getJobClass());

        $response->assertOk();
        $response->assertJsonFragment([
            'state' => $this->versionSetSuccessfulState->getState()->value,
            'message' => $this->versionSetSuccessfulState->getMessage(),
        ]);

        $newVersion = $originalVersion->Media->Versions()->whereNumber($response->json('version'))->first();

        $this->assertModelExists($originalVersion);
        $this->assertModelExists($newVersion);
        $this->assertNotEquals($originalVersion->id, $newVersion->id);
        $this->assertSame($originalVersion->filename, $newVersion->filename);
        $this->assertFalse((bool)$newVersion->processed);
    }

    #[Test]
    public function canPurgeDerivatives(): void
    {
        Queue::fake();

        $version = $this->performUpload();
        Queue::assertPushed(Transcode::getJobClass());

        $media = $version->Media;
        $latestVersionNumberBeforePurging = $media->latestVersion->number;

        Artisan::call(PurgeDerivatives::class, ['--video' => true]);
        Queue::assertPushed(Transcode::getJobClass());
        Queue::assertPushed(ClientPurgeNotification::class);

        $media->refresh();
        $latestVersion = $media->latestVersion;

        $this->assertSame($latestVersionNumberBeforePurging + 1, $latestVersion->number);
        $this->assertFalse((bool)$latestVersion->processed);
    }
}

