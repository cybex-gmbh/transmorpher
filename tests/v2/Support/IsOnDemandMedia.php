<?php

namespace Tests\v2\Support;

use App\Models\Version;
use Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

trait IsOnDemandMedia
{
    protected function getOriginal(Version $version): TestResponse
    {
        return $this->get(route(
            sprintf('v2.get%sOriginal', ucfirst($this->mediaType->value)),
            [$version->Media, $version]
        ));
    }

    protected function getDerivativeForVersion(Version $version, string $transformations = ''): TestResponse
    {
        $params = [$version->Media, $version];

        if ($transformations !== '') {
            $params[] = $transformations;
        }

        return $this->get(route(
            sprintf('v2.get%sDerivativeForVersion', ucfirst($this->mediaType->value)),
            $params
        ));
    }

    protected function getPublicDerivative(Version $version, string $transformations = ''): TestResponse
    {
        $params = [$this->user, $version->Media];

        if ($transformations !== '') {
            $params[] = $transformations;
        }

        Auth::forgetGuards();

        try {
            return $this->get(route(
                sprintf('get%sDerivative', ucfirst($this->mediaType->value)),
                $params
            ));
        } finally {
            Sanctum::actingAs($this->user, ['*']);
        }
    }
}

