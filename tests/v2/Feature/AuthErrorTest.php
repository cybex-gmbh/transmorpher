<?php

namespace Tests\v2\Feature;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\v2\Support\MediaHelper;

class AuthErrorTest extends MediaHelper
{
    protected MediaType $mediaType = MediaType::IMAGE;
    protected MediaStorage $derivativesStorage = MediaStorage::IMAGE_DERIVATIVES;
    protected string $identifier = 'test-v2-auth-error';
    protected string $mediaFileFilePath = 'tests/data/test.png';

    #[Test]
    #[DataProvider('protectedRouteProvider')]
    public function rejectsUnauthenticatedAccessToProtectedV2Routes(string $method, string $route): void
    {
        $response = $this->asGuest(fn() => $this->json($method, $route));

        $response->assertUnauthorized();
        $response->assertJsonStructure(['message']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedRouteProvider(): array
    {
        return [
            'list versions' => ['GET', '/api/v2/media/placeholder-media/versions'],
            'delete media' => ['DELETE', '/api/v2/media/placeholder-media'],
            'set version' => ['PATCH', '/api/v2/media/placeholder-media/version/1'],
            'image original' => ['GET', '/api/v2/image/placeholder-media/version/1/original'],
            'image derivative for version' => ['GET', '/api/v2/image/placeholder-media/version/1/derivative/q-1'],
            'document original' => ['GET', '/api/v2/document/placeholder-media/version/1/original'],
            'document derivative for version' => ['GET', '/api/v2/document/placeholder-media/version/1/derivative/q-1'],
            'reserve upload slot' => ['POST', '/api/v2/image/reserveUploadSlot'],
            'chunk upload url' => ['GET', '/api/v2/upload/placeholder-upload-slot/chunkUrl/1'],
            'complete upload' => ['POST', '/api/v2/upload/placeholder-upload-slot/complete'],
            'abort upload' => ['DELETE', '/api/v2/upload/placeholder-upload-slot'],
        ];
    }

    protected function asGuest(callable $request): mixed
    {
        Auth::forgetGuards();

        try {
            return $request();
        } finally {
            Sanctum::actingAs($this->user, ['*']);
        }
    }
}



