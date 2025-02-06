<?php

namespace App\Classes\MediaHandler;

use App\Enums\ResponseState;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use CdnHelper;
use Throwable;

abstract class StaticMediaHandler extends MediaHandler
{
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, Version $version): ResponseState
    {
        if ($this->invalidateCdnCache($basePath)) {
            /**
             * This prevents CDN cache pollution.
             *
             * Explanation:
             * 1. new version is uploaded
             * 2. media is requested and new version is delivered
             * 3. cache invalidation fails, version gets deleted
             * 4. now nonexistent version is still in the CDN cache
             */
            $version->update(['processed' => true]);

            return $this->uploadSuccessful;
        }

        return $this->uploadFailed;
    }

    /**
     * @param string $basePath
     * @return bool
     */
    public function invalidateCdnCache(string $basePath): bool
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateMedia($this->type, $basePath);
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param User $user
     * @param Version $version
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     * @return array
     */
    public function setVersion(User $user, Version $version, int $oldVersionNumber, bool $wasProcessed): array
    {
        // Token and valid_until will be set in the 'saving' event.
        // By creating an upload slot, a currently active upload will be canceled.
        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(['identifier' => $version->Media->identifier], ['media_type' => $this->type]);

        if ($this->invalidateCdnCache($version->Media->baseDirectory())) {
            $version->update(['processed' => true]);
            $responseState = $this->versionSetSuccessful;
        } else {
            $version->update(['number' => $oldVersionNumber]);
            $responseState = $this->versionSetFailed;
        }

        return [
            $responseState,
            $uploadSlot->token
        ];
    }

    /**
     * @param Media $media
     * @return array
     */
    public function getVersions(Media $media): array
    {
        $processedVersions = $media->Versions()->where('processed', true)->get();
        $currentVersionNumber = $processedVersions->max('number');

        return [
            'currentVersion' => $currentVersionNumber,
            'currentlyProcessedVersion' => $currentVersionNumber,
            'versions' => $processedVersions->pluck('created_at', 'number')->map(fn($date) => strtotime($date)),
        ];
    }

    /**
     * @return array
     */
    public function purgeDerivatives(): array
    {
        $success = $this->getDerivativesDisk()->deleteDirectory('');

        return [
            'success' => $success,
            'message' => $success ? sprintf('Deleted %s derivatives.', $this->type->value) : sprintf('Failed to delete %s derivatives.', $this->type->value),
        ];
    }
}
