<?php

namespace Tests;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Storage;

class MediaTest extends TestCase
{
    protected static User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::persistentFake(config('transmorpher.disks.originals'));

        Sanctum::actingAs(
            self::$user ??= User::factory()->create(),
            ['*']
        );
    }
}
