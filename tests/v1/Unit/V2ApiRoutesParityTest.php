<?php

namespace Tests\v1\Unit;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V2ApiRoutesParityTest extends TestCase
{
    #[Test]
    public function v2ContainsV1EquivalentRoutesThatAreRelevantForClients(): void
    {
        $routeNames = [
            'v2.getVersions',
            'v2.delete',
            'v2.setVersion',
            'v2.getImageOriginal',
            'v2.getImageDerivativeForVersion',
            'v2.getDocumentOriginal',
            'v2.getDocumentDerivativeForVersion',
            'v2.reserveUploadSlot',
            'v2.upload',
            'v2.getPublicKey',
            'v2.getCacheInvalidator',
        ];

        foreach ($routeNames as $routeName) {
            $this->assertTrue(Route::has($routeName), sprintf('Expected route "%s" to be registered.', $routeName));
        }
    }
}

