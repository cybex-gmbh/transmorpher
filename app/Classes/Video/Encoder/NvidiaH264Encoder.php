<?php

namespace App\Classes\Video\Encoder;

class NvidiaH264Encoder extends AbstractEncoder
{
    public function name(): string
    {
        return 'nvidia-h264';
    }
}
