<?php

namespace App\Classes\Video\Encoder;

class CpuH264Encoder extends AbstractEncoder
{
    public function name(): string
    {
        return 'cpu-h264';
    }
}
