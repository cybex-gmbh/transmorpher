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
            ->autoGenerateRepresentations(config('transmorpher.representations'))
            // Omit data streams(-dn) and attachments(-map -0:t?).
            // Data streams are things such as timecodes, an attachment may be metadata.
            // Transcoding sometimes failed when data streams where not omitted. Metadata should not be publicly available.
            ->setAdditionalParams(['-dn', '-map', '-0:t?']);
    }
}
