<?php

namespace App\Classes\MediaHandler;

use App\Enums\ResponseState;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;

abstract class OnDemandDerivativeMediaHandler extends MediaHandler
{
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, Version $version): ResponseState
    {
        if ($this->invalidateCdnCache($basePath)) {
            /**
             * This prevents CDN cache pollution.
             *
             * Explanation on how pollution would happen:
             * 1. a new version is uploaded
             * 2. media is requested and the version is delivered
             * 3. cache invalidation fails, the version gets deleted
             * 4. now the non-existent version is still in the CDN cache
             */
            $version->update(['processed' => true]);

            return $this->uploadSuccessful;
        }

        return $this->uploadFailed;
    }

    /**
     * This will create a new upload slot, so ongoing uploads/processings are aborted.
     *
     * @param User $user
     * @param Version $version
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     * @return array
     */
    public function setVersion(User $user, Version $version, int $oldVersionNumber, bool $wasProcessed): array
    {
        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(['identifier' => $version->Media->identifier], ['media_type' => $this->type]);

        if ($this->invalidateCdnCache($version->Media->baseDirectory())) {
            $version->update(['processed' => true]);
            $responseState = $this->versionSetSuccessful;
        } else {
            $version->delete();
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
    public function deleteDerivatives(): array
    {
        $success = $this->getDerivativesDisk()->deleteDirectory('');

        return [
            'success' => $success,
            'message' => $success ? sprintf('Deleted %s derivatives.', $this->type->value) : sprintf('Failed to delete %s derivatives.', $this->type->value),
        ];
    }
}
