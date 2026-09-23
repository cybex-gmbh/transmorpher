<?php

namespace App\Enums;

enum ClientNotification: string
{
    case VIDEO_TRANSCODING = 'video_transcoding';
    case CACHE_INVALIDATION = 'cache_invalidation';
}
