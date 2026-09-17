<?php

namespace App\Classes\Video\Encoder;

class NvidiaHevcEncoder extends AbstractEncoder
{
    public function name(): string
    {
        return 'nvidia-hevc';
    }
}
