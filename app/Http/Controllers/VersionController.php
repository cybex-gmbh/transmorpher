<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\UploadState;
use App\Http\Requests\SetVersionRequest;
use App\Models\Media;
use App\Models\Version;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    /**
     * Retrieve all version numbers for the given identifier.
     *
     * @param Request $request
     * @param Media $media
     * @return JsonResponse
     */
    public function getVersions(Request $request, Media $media): JsonResponse
    {
        return response()->json(
            array_merge([
                'state' => ResponseState::VERSIONS_RETRIEVED->getState()->value,
                'message' => ResponseState::VERSIONS_RETRIEVED->getMessage(),
                'identifier' => $media->identifier,
            ],
                $media->type->handler()->getVersions($media)
            )
        );
    }

    /**
     * Sets a version as current version.
     *
     * @param SetVersionRequest $request
     * @param Media $media
     * @param Version $version
     * @return JsonResponse
     */
    public function setVersion(SetVersionRequest $request, Media $media, Version $version): JsonResponse
    {
        $user = $request->user();
        $wasProcessed = $version->processed;
        $oldVersionNumber = $version->number;
        $currentVersionNumber = $media->Versions->max('number');
        $newVersionNumber = $currentVersionNumber + 1;

        $version->update(['number' => $newVersionNumber, 'processed' => 0]);

        [$responseState, $uploadToken] = $media->type->handler()->setVersion($user, $media, $version, $oldVersionNumber, $wasProcessed, $request->get('callback_url'));

        return response()->json([
            'state' => $responseState->getState()->value,
            'message' => $responseState->getMessage(),
            'identifier' => $media->identifier,
            'version' => $responseState->getState() !== UploadState::ERROR ? $newVersionNumber : $currentVersionNumber,
            // Base path is only passed for images since the video is not available at this path yet.
            'public_path' => $media->type === MediaType::IMAGE ? FilePathHelper::toBaseDirectory($media) : null,
            'upload_token' => $uploadToken
        ]);
    }

    /**
     * Deletes all data for a given identifier.
     *
     * @param Request $request
     * @param Media $media
     * @return JsonResponse
     */
    public function delete(Request $request, Media $media): JsonResponse
    {
        $basePath = FilePathHelper::toBaseDirectory($media);

        if ($media->type->handler()->invalidateCdnCache($basePath)) {
            $media->Versions()->delete();
            $media->type->handler()->getDerivativesDisk()->deleteDirectory($basePath);
            MediaStorage::ORIGINALS->getDisk()->deleteDirectory($basePath);
            $media->delete();

            $responseState = $media->type->handler()->invalidateCdnCache($basePath) ? ResponseState::DELETION_SUCCESSFUL : ResponseState::CDN_INVALIDATION_FAILED;
        } else {
            $responseState = ResponseState::CDN_INVALIDATION_FAILED;
        }

        return response()->json([
            'state' => $responseState->getState()->value,
            'message' => $responseState->getMessage(),
            'identifier' => $media->identifier,
        ]);
    }
}
