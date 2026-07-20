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
    public function rejectsUnauthenticatedAccessToProtectedV2Routes(string $method, string $routeName, string $paramsType): void
    {
        $routeParameters = match ($paramsType) {
            'media' => ['placeholder-media'],
            'media_version' => ['placeholder-media', 1],
            'media_version_transformation' => ['placeholder-media', 1, 'q-1'],
            'media_type' => [$this->mediaType],
            'upload_slot' => ['placeholder-upload-slot'],
            'upload_slot_chunk' => ['placeholder-upload-slot', 1],
        };

        $response = $this->asGuest(fn() => $this->json($method, route($routeName, $routeParameters)));

        $response->assertUnauthorized();
        $response->assertJsonStructure(['message']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function protectedRouteProvider(): array
    {
        return [
            'list versions' => ['GET', 'v2.getVersions', 'media'],
            'delete media' => ['DELETE', 'v2.delete', 'media'],
            'set version' => ['PATCH', 'v2.setVersion', 'media_version'],
            'image original' => ['GET', 'v2.getImageOriginal', 'media_version'],
            'image derivative for version' => ['GET', 'v2.getImageDerivativeForVersion', 'media_version_transformation'],
            'document original' => ['GET', 'v2.getDocumentOriginal', 'media_version'],
            'document derivative for version' => ['GET', 'v2.getDocumentDerivativeForVersion', 'media_version_transformation'],
            'reserve upload slot' => ['POST', 'v2.reserveUploadSlot', 'media_type'],
            'chunk upload url' => ['GET', 'v2.chunkUploadUrl', 'upload_slot_chunk'],
            'complete upload' => ['POST', 'v2.completeUpload', 'upload_slot'],
            'abort upload' => ['DELETE', 'v2.abortUpload', 'upload_slot'],
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



