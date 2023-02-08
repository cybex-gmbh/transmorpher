<?php

namespace App\Enums;

use Streaming\Media as StreamingMedia;
use Streaming\Streaming;

enum StreamingFormat: string
{
    case HLS = 'hls';
    case DASH = 'dash';

    /**
     * @param StreamingMedia $video
     *
     * @return Streaming The video configured with the streaming format, codec and representations.
     */
    public function configure(StreamingMedia $video): Streaming
    {
        $format = $this->value;
        $codec  = config('transmorpher.video_codec');

        return $video->$format()
            ->$codec()
            ->autoGenerateRepresentations(config('transmorpher.representations'));
    }
}
