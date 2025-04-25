<?php

namespace Tests;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Storage;

abstract class MediaTest extends TestCase
{
    protected User $user;
    protected Filesystem $originalsDisk;
    protected Filesystem $derivativesDisk;
    protected ResponseState $versionSetSuccessful;
    protected string $identifier;
    protected string $mediaName;
    protected MediaType $mediaType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalsDisk ??= Storage::persistentFake(MediaStorage::ORIGINALS->getDiskName());

        Sanctum::actingAs(
            $this->user ??= User::first() ?: User::factory()->create(),
            ['*']
        );
    }

    protected function reserveUploadSlot(): TestResponse
    {
        return $this->json('POST', route('v1.reserveUploadSlot', $this->mediaType), [
            'identifier' => $this->identifier
        ]);
    }

    #[Test]
    public function ensureUploadSlotCanBeReserved()
    {
        $reserveUploadSlotResponse = $this->reserveUploadSlot();

        $reserveUploadSlotResponse->assertOk();

        return $reserveUploadSlotResponse->json()['upload_token'];
    }
}
