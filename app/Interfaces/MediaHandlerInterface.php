<?php

namespace App\Interfaces;

use App\Enums\ResponseState;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use Illuminate\Contracts\Filesystem\Filesystem;

interface MediaHandlerInterface
{
    /**
     * @param string $basePath
     * @param UploadSlot $uploadSlot
     * @param Version $version
     * @return ResponseState
     */
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, Version $version): ResponseState;

    /**
     * @deprecated Will be removed once v1 has been discontinued.
     *             Use {@link getAllowedMimetypes()} instead.
     */
    public function getValidationRules(): string;

    public function getAllowedMimetypes(): string;

    public function isMimeTypeValid(string $mimeType): bool;

    /**
     * @param string $basePath
     * @return bool
     */
    public function invalidateCdnCache(string $basePath): bool;

    /**
     * @param User $user
     * @param Version $version
     * @return array
     */
    public function processVersion(User $user, Version $version): array;

    /**
     * @return Filesystem
     */
    public function getDerivativesDisk(): Filesystem;

    /**
     * @return array
     */
    public function deleteDerivatives(): array;

    /**
     * @param Version $version
     * @param array|null $transformationsArray
     * @return false|string
     */
    public function applyTransformations(Version $version, ?array $transformationsArray): false|string;
}
