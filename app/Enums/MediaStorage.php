<?php

namespace App\Enums;

use Illuminate\Filesystem\FilesystemAdapter;
use Storage;

enum MediaStorage: string
{
    case ORIGINALS = 'originals';
    case IMAGE_DERIVATIVES = 'imageDerivatives';
    case DOCUMENT_DERIVATIVES = 'documentDerivatives';
    case VIDEO_DERIVATIVES = 'videoDerivatives';

    /**
     * Retrieve storage disk from the value specified in the transmorpher config.
     *
     * @return FilesystemAdapter
     */
    public function getDisk(): FilesystemAdapter
    {
        return Storage::disk($this->getDiskName());
    }

    public function getDiskName(): string
    {
        return config(sprintf('transmorpher.disks.%s', $this->value));
    }
}
