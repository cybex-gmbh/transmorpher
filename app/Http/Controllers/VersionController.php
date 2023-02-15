<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Http\Requests\SetVersionRequest;
use App\Models\Media;
use App\Models\User;
use App\Models\Version;
use CdnHelper;
use Exception;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Transcode;

class VersionController extends Controller
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
        $user                   = $request->user();
        $media                  = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $versions               = $media->Versions;
        $currentVersionNumber   = $versions->max('number');
        $processedVersionNumber = $media->type === MediaType::VIDEO ? $media->Versions()->whereProcessed(true)->max('number') : null;
        $allVersionNumbers      = $versions->pluck('number');

        return response()->json([
            'success'                   => true,
            'response'                  => 'Successfully retrieved version numbers.',
            'identifier'                => $identifier,
            'currentVersion'            => $currentVersionNumber ?? null,
            'currentlyProcessedVersion' => $processedVersionNumber,
            'versions'                  => $allVersionNumbers,
            'client'                    => $user->name,
        ]);
    }

    /**
     * Sets a version as current version.
     *
     * @param SetVersionRequest $request
     * @param string            $identifier
     * @param string            $versionNumber
     *
     * @return JsonResponse
     */
    public function setVersion(SetVersionRequest $request, string $identifier, string $versionNumber): JsonResponse
    {
        $user             = $request->user();
        $media            = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $version          = $media->Versions()->whereNumber($versionNumber)->firstOrFail();
        $wasProcessed     = $version->processed;
        $oldVersionNumber = $version->number;
        $newVersionNumber = $media->Versions()->max('number') + 1;

        $version->update(['number' => $newVersionNumber, 'processed' => 0]);

        if ($media->type === MediaType::VIDEO) {
            [$success, $response] = $this->setVideoVersion(
                $request->get('callback_url'),
                $request->get('id_token'),
                $user,
                $identifier,
                $media,
                $version,
                $oldVersionNumber,
                $wasProcessed
            );
        } else {
            // Media type is image.
            $basePath = FilePathHelper::toBaseDirectory($user, $identifier);

            if (CdnHelper::isConfigured()) {
                try {
                    $invalidationPaths = [
                        sprintf('/%s', $basePath),
                        sprintf('/%s/', $basePath),
                        sprintf('/%s/*', $basePath),
                    ];

                    CdnHelper::invalidate($invalidationPaths);
                } catch (Exception) {
                    $version->update(['number' => $oldVersionNumber]);

                    $success  = false;
                    $response = 'Cache invalidation failed.';
                }
            }
        }

        return response()->json([
            'success'     => $success ??= true,
            'response'    => $response ?? 'Successfully set image version.',
            'identifier'  => $identifier,
            'version'     => $success ? $newVersionNumber : $oldVersionNumber,
            'client'      => $user->name,
            'public_path' => $basePath ?? null,
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
        $basePath = FilePathHelper::toBaseDirectory($user, $identifier);
        $success = null;

        // This will make sure we can invalidate the cache and prevent deleting the media before we are sure it will work.
        if (CdnHelper::isConfigured()) {
            try {
                $invalidationPaths = [
                    sprintf('/%s', $basePath),
                    sprintf('/%s/', $basePath),
                    sprintf('/%s/*', $basePath),
                ];

                CdnHelper::invalidate($invalidationPaths);
            } catch (Exception) {
                $success  = false;
                $response = 'Cache invalidation failed.';
            }
        }

        if (is_null($success)) {
            $media->Versions()->delete();
            ($media->type === MediaType::IMAGE ? MediaStorage::IMAGE_DERIVATIVES : MediaStorage::VIDEO_DERIVATIVES)->getDisk()->deleteDirectory($basePath);
            MediaStorage::ORIGINALS->getDisk()->deleteDirectory($basePath);
            $media->delete();

            if (CdnHelper::isConfigured()) {
                CdnHelper::invalidate($invalidationPaths);
            }
        }

        return response()->json([
            'success'    => $success ?? true,
            'response'   => $response ?? 'Successfully deleted media.',
            'identifier' => $identifier,
            'client'     => $user->name,
        ]);
    }

    /**
     * @param string  $callbackUrl
     * @param string  $idToken
     * @param mixed   $user
     * @param string  $identifier
     * @param Media   $media
     * @param Version $version
     * @param int     $oldVersionNumber
     * @param bool    $wasProcessed
     *
     * @return array
     */
    protected function setVideoVersion(string $callbackUrl, string $idToken, User $user, string $identifier, Media $media, Version $version, int $oldVersionNumber, bool $wasProcessed): array
    {
        if ($callbackUrl && $idToken) {
            $filePath = FilePathHelper::toOriginalFile($user, $identifier, $version->number);
            $success  = Transcode::createJobForVersionUpdate($filePath, $media, $version, $callbackUrl, $idToken, $oldVersionNumber, $wasProcessed);
            $response = $success ? 'Successfully set video version, transcoding job has been dispatched.' : 'Could not create transcoding job';
        } else {
            $version->update([
                'number'    => $oldVersionNumber,
                'processed' => $wasProcessed,
            ]);

            $success  = false;
            $response = 'A callback URL and an identification token is needed for this identifier.';
        }

        return [
            $success,
            $response,
        ];
    }
}
