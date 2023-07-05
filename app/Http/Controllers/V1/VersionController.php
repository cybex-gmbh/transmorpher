<?php

namespace App\Http\Controllers\V1;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SetVersionRequest;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ItemNotFoundException;

class VersionController extends Controller
{
    /**
     * Retrieve all version numbers for the given identifier.
     *
     * @param Request $request
     * @param string $identifier
     *
     * @return JsonResponse
     */
    public function getVersions(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        $media = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $currentVersionNumber = $media->Versions->max('number');
        $processedVersionNumber = $media->type === MediaType::VIDEO ? $media->Versions->where('processed', true)->max('number') : null;
        $allVersionNumbers = $media->Versions->pluck('created_at', 'number')->map(fn($date) => strtotime($date));

        return response()->json([
            'success' => ResponseState::VERSIONS_RETRIEVED->success(),
            'response' => ResponseState::VERSIONS_RETRIEVED->getResponse(),
            'identifier' => $identifier,
            'currentVersion' => $currentVersionNumber ?? null,
            'currentlyProcessedVersion' => $processedVersionNumber,
            'versions' => $allVersionNumbers,
            'client' => $user->name,
        ]);
    }

    /**
     * Sets a version as current version.
     *
     * @param SetVersionRequest $request
     * @param string $identifier
     * @param string $versionNumber
     *
     * @return JsonResponse
     */
    public function setVersion(SetVersionRequest $request, string $identifier, string $versionNumber): JsonResponse
    {
        $user = $request->user();
        $media = $user->Media()->whereIdentifier($identifier)->firstOrFail();

        try {
            $version = $media->Versions->where('number', $versionNumber)->firstOrFail();
        } catch (ItemNotFoundException $exception) {
            abort(404);
        }

        $wasProcessed = $version->processed;
        $oldVersionNumber = $version->number;
        $currentVersionNumber = $media->Versions->max('number');
        $newVersionNumber = $currentVersionNumber + 1;

        $version->update(['number' => $newVersionNumber, 'processed' => 0]);

        [$responseState, $uploadToken] = $media->type->handler()->setVersion($user, $media, $version, $oldVersionNumber, $wasProcessed, $request->get('callback_url'));

        return response()->json([
            'success' => $responseState->success(),
            'response' => $responseState->getResponse(),
            'identifier' => $identifier,
            'version' => $responseState->success() ? $newVersionNumber : $currentVersionNumber,
            'client' => $user->name,
            // Base path is only passed for images since the video is not available at this path yet.
            'public_path' => $media->type === MediaType::IMAGE ? FilePathHelper::toBaseDirectory($user, $media->identifier) : null,
            'upload_token' => $uploadToken
        ]);
    }

    /**
     * Deletes all data for a given identifier.
     *
     * @param Request $request
     * @param string $identifier
     *
     * @return JsonResponse
     */
    public function delete(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        $media = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $basePath = FilePathHelper::toBaseDirectory($user, $identifier);

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
            'success' => $responseState->success(),
            'response' => $responseState->getResponse(),
            'identifier' => $identifier,
            'client' => $user->name,
        ]);
    }
}
