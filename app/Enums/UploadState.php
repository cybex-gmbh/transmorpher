<?php

namespace App\Enums;

enum UploadState: string
{
    case DELETED = 'deleted';
    case ERROR = 'error';
    case INITIALIZING = 'initializing';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case UPLOADING = 'uploading';
}
