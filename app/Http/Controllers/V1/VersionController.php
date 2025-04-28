<?php

namespace App\Http\Controllers\V1;

use App\Enums\ResponseState;
use App\Enums\UploadState;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SetVersionRequest;
use App\Models\Media;
use App\Models\Version;
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
     * Sets a version as the current version.
     *
     * @param SetVersionRequest $request
     * @param Media $media
     * @param Version $version
     * @return JsonResponse
     */
    public function processVersion(SetVersionRequest $request, Media $media, Version $version): JsonResponse
    {
        $user = $request->user();
        $currentlyProcessedVersionNumber = $media->Versions->where('processed', true)->max('number');
        $newVersionNumber = $media->Versions->max('number') + 1;

        $version = $version->replicate()->fill([
            'number' => $newVersionNumber,
            'processed' => 0,
        ]);
        $version->save();

        [$responseState, $uploadToken] = $media->type->handler()->processVersion($user, $version);

        return response()->json([
            'state' => $responseState->getState()->value,
            'message' => $responseState->getMessage(),
            'identifier' => $media->identifier,
            'version' => $responseState->getState() !== UploadState::ERROR ? $newVersionNumber : $currentlyProcessedVersionNumber,
            // Base path is only passed for images since the video is not available at this path yet.
            'public_path' => $media->type->isInstantlyAvailable() ?
                implode(DIRECTORY_SEPARATOR, array_filter([$media->type->prefix(), $media->baseDirectory()]))
                : null,
            'upload_token' => $uploadToken,
            'hash' => $media->type->isInstantlyAvailable() ? $version->hash : null,
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
        $basePath = $media->baseDirectory();

        if ($media->type->handler()->invalidateCdnCache($basePath)) {
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
