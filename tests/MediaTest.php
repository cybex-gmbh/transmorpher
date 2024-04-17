<?php

namespace Tests;

use App\Enums\MediaStorage;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Laravel\Sanctum\Sanctum;
use Storage;

class MediaTest extends TestCase
{
    protected static User $user;
    protected Filesystem $originalsDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalsDisk ??= Storage::persistentFake(config(sprintf('transmorpher.disks.%s', MediaStorage::ORIGINALS->value)));

        Sanctum::actingAs(
            self::$user ??= User::factory()->create(),
            ['*']
        );
    }
}
