<?php

namespace App\Enums;

enum UploadState: string
{
    case DELETED = 'deleted';
    case ERROR = 'error';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
}
