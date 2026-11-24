<?php

namespace Tests\v2\Support;

use App\Models\Version;
use Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

trait HandlesOnDemandMedia
{
    protected function getOriginal(Version $version): TestResponse
    {
        return $this->get($this->versionOriginalRoute($version->Media->identifier, $version->number));
    }

    protected function getDerivativeForVersion(Version $version, string $transformations = ''): TestResponse
    {
        return $this->get($this->versionDerivativeRoute($version->Media->identifier, $version->number, $transformations));
    }

    protected function getPublicDerivative(Version $version, string $transformations = ''): TestResponse
    {
        Auth::forgetGuards();

        try {
            return $this->get($this->publicDerivativeRoute($this->user->name, $version->Media->identifier, $transformations));
        } finally {
            Sanctum::actingAs($this->user, ['*']);
        }
    }

    protected function versionOriginalRoute(string $mediaIdentifier, int $versionNumber): string
    {
        return sprintf('/api/v2/%s/%s/versions/%d/original', $this->mediaType->value, $mediaIdentifier, $versionNumber);
    }

    protected function versionDerivativeRoute(string $mediaIdentifier, int $versionNumber, string $transformations = ''): string
    {
        return sprintf('/api/v2/%s/%s/versions/%d/derivative/%s', $this->mediaType->value, $mediaIdentifier, $versionNumber, $transformations);
    }

    protected function publicDerivativeRoute(string $userName, string $mediaIdentifier, string $transformations = ''): string
    {
        return sprintf('/%s/%s/%s/%s', $this->mediaType->prefix(), $userName, $mediaIdentifier, $transformations);
    }
}
