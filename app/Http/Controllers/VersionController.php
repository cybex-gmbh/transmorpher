<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Http\Requests\SetVersionRequest;
use App\Models\Media;
use App\Models\User;
use App\Models\Version;
use CdnHelper;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ItemNotFoundException;
use Throwable;
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
        $currentVersionNumber   = $media->Versions->max('number');
        $processedVersionNumber = $media->type === MediaType::VIDEO ? $media->Versions->where('processed', true)->max('number') : null;
        $allVersionNumbers      = $media->Versions->pluck('created_at', 'number')->map(fn($date) => strtotime($date));;

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
        $user  = $request->user();
        $media = $user->Media()->whereIdentifier($identifier)->firstOrFail();

        try {
            $version = $media->Versions->where('number', $versionNumber)->firstOrFail();
        } catch (ItemNotFoundException $exception) {
            abort(404);
        }

        $wasProcessed         = $version->processed;
        $oldVersionNumber     = $version->number;
        $currentVersionNumber = $media->Versions->max('number');
        $newVersionNumber     = $currentVersionNumber + 1;
        $success              = null;

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
                    CdnHelper::invalidateImage($basePath);
                } catch (Throwable) {
                    $version->update(['number' => $oldVersionNumber]);

                    $success  = false;
                    $response = 'Cache invalidation failed.';
                }
            }

            if (is_null($success)) {
                // Might instead move the directory to keep derivatives, but S3 can't move directories and each file would have to be moved individually.
                MediaStorage::IMAGE_DERIVATIVES->getDisk()->deleteDirectory(FilePathHelper::toImageDerivativeVersionDirectory($user, $identifier, $oldVersionNumber));
            }
        }

        return response()->json([
            'success'     => $success ??= true,
            'response'    => $response ?? 'Successfully set image version.',
            'identifier'  => $identifier,
            'version'     => $success ? $newVersionNumber : $currentVersionNumber,
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

        // This will make sure we can invalidate the cache before the media gets deleted.
        if (CdnHelper::isConfigured()) {
            try {
                $media->type === MediaType::IMAGE ? CdnHelper::invalidateImage($basePath) : CdnHelper::invalidateVideo($basePath);
            } catch (Throwable) {
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
                $media->type === MediaType::IMAGE ? CdnHelper::invalidateImage($basePath) : CdnHelper::invalidateVideo($basePath);
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
     * Handles the dispatching of a new transcoding job when setting a video version.
     *
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
            $response = 'A callback URL and an identification token are needed for this identifier.';
        }

        return [
            $success,
            $response,
        ];
    }
}
