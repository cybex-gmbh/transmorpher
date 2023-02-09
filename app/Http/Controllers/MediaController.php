<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Http\Requests\SetVersionRequest;
use CdnHelper;
use Exception;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Transcode;

class MediaController extends Controller
{
    /**
     * Retrieve all version numbers for the given identifier.
     *
     * @param Request $request
     * @param string  $identifier
     *
     * @return JsonResponse
     */
    public function getVersions(Request $request, string $identifier): JsonResponse
    {
        $user                 = $request->user();
        $media                = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $versions             = $media->Versions;
        $currentVersionNumber = $versions->max('number');
        $allVersionNumbers    = $versions->pluck('number');

        return response()->json([
            'success'        => true,
            'response'       => 'Successfully retrieved version numbers.',
            'identifier'     => $identifier,
            'currentVersion' => $currentVersionNumber ?? null,
            'versions'       => $allVersionNumbers,
            'client'         => $user->name,

        ]);
    }

    /**
     * Sets a version as current version.
     *
     * @param SetVersionRequest $request
     * @param string            $identifier
     * @param string            $newVersionNumber
     *
     * @return JsonResponse
     */
    public function setVersion(SetVersionRequest $request, string $identifier, string $newVersionNumber): JsonResponse
    {
        $user             = $request->user();
        $media            = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $version          = $media->Versions()->whereNumber($newVersionNumber)->firstOrFail();
        $oldVersionNumber = $version->number;
        $newVersionNumber = $media->Versions()->max('number') + 1;

        $version->update(['number' => $newVersionNumber, 'processed' => 0]);

        if ($media->type === MediaType::VIDEO) {
            if ($callbackUrl = $request->input('callback_url') && $idToken = $request->get('id_token')) {
                $filePath = FilePathHelper::getPathToOriginal($user, $identifier);
                $success  = Transcode::createJobForVersionUpdate($filePath, $media, $version, $callbackUrl, $idToken, $oldVersionNumber);

                if ($success) {
                    $response = 'Successfully set video version, transcoding job has been dispatched.';
                } else {
                    $response         = 'Could not create transcoding job';
                    $newVersionNumber = $oldVersionNumber;
                }
            } else {
                $version->update(['number' => $oldVersionNumber]);

                $success          = false;
                $response         = 'A callback URL and an identification token is needed for this identifier.';
                $newVersionNumber = $oldVersionNumber;
            }
        } else {
            $success = true;

            if (CdnHelper::isConfigured()) {
                try {
                    CdnHelper::invalidate(sprintf('/%s/*',
                        MediaStorage::IMAGE_DERIVATIVES->getDisk()->path(FilePathHelper::getBasePath($user, $identifier))));
                } catch (Exception) {
                    $version->update(['number' => $oldVersionNumber]);

                    $success          = false;
                    $response         = 'Cache invalidation failed.';
                    $newVersionNumber = $oldVersionNumber;
                }
            }
        }

        return response()->json([
            'success'    => $success,
            'response'   => $response ?? 'Successfully set image version.',
            'identifier' => $identifier,
            'version'    => $newVersionNumber,
            'client'     => $user->name,
        ]);
    }

    /**
     * Deletes all data for a given identifier.
     *
     * @param Request $request
     * @param string  $identifier
     *
     * @return JsonResponse
     */
    public function delete(Request $request, string $identifier): JsonResponse
    {
        $user     = $request->user();
        $media    = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $basePath = FilePathHelper::getBasePath($user, $identifier);

        $success  = true;
        $response = 'Successfully deleted media.';

        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidate(sprintf('/%s/*',
                    MediaStorage::IMAGE_DERIVATIVES->getDisk()->path($basePath)));
            } catch (Exception) {
                $success  = false;
                $response = 'Cache invalidation failed.';
            }
        }

        $media->Versions()->delete();
        ($media->type === MediaType::IMAGE ? MediaStorage::IMAGE_DERIVATIVES : MediaStorage::VIDEO_DERIVATIVES)->getDisk()->deleteDirectory($basePath);
        MediaStorage::ORIGINALS->getDisk()->deleteDirectory($basePath);
        $media->delete();

        if (CdnHelper::isConfigured()) {
            CdnHelper::invalidate(sprintf('/%s/*', MediaStorage::IMAGE_DERIVATIVES->getDisk()->path($basePath)));
        }

        return response()->json([
            'success'    => $success,
            'response'   => $response,
            'identifier' => $identifier,
            'client'     => $user->name,
        ]);
    }
}
