<?php

namespace App\Classes\Video\Encoder;

class CpuHevcEncoder extends AbstractEncoder
{
    public function name(): string
    {
        return 'cpu-hevc';
    }
}
