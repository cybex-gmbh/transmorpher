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
     * @param Encoder $encoder
     *
     * @return Streaming The video configured with the streaming format, codec and representations.
     */
    public function configure(StreamingMedia $video, Encoder $encoder): Streaming
    {
        $format = $this->value;
        $codec  = $encoder->getStreamingCodec();

        // GPU accelerated encoding cannot be set via $codec('h264_nvenc'). It may be set through the additional params.
        return $video->$format()
            ->$codec()
            ->autoGenerateRepresentations(config('transmorpher.representations'))
            ->setAdditionalParams(config('transmorpher.additional_transcoding_parameters'));
    }
}
