<?php

namespace Tests;

use App\Enums\MediaStorage;
use App\Enums\ResponseState;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Laravel\Sanctum\Sanctum;
use Storage;

class StaticMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
}
