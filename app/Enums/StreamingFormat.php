<?php

namespace App\Enums;

use Encoder;
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
        $codec = Encoder::streamingCodec();

        // GPU accelerated encoding cannot be set via $codec('h264_nvenc'). It may be set through the additional params.
        return $video->$format()
            ->$codec()
            ->autoGenerateRepresentations(config('transmorpher.media.video.representations'))
            ->setAdditionalParams(Encoder::outputParameters());
    }
}
