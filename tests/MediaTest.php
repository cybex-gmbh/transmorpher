<?php

namespace Tests;

use App\Enums\MediaStorage;
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
    protected string $reserveUploadSlotRouteName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalsDisk ??= Storage::persistentFake(config(sprintf('transmorpher.disks.%s', MediaStorage::ORIGINALS->value)));

        Sanctum::actingAs(
            $this->user ??= User::first() ?: User::factory()->create(),
            ['*']
        );
    }

    protected function reserveUploadSlot(): TestResponse
    {
        return $this->json('POST', route($this->reserveUploadSlotRouteName), [
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
