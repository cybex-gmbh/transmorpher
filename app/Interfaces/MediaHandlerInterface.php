<?php

namespace App\Interfaces;

use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\Version;

interface MediaHandlerInterface
{
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, string $filePath, Media $media, Version $version);

    public function getValidationRules(): string;
}
