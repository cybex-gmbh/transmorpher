<?php

namespace App\Classes\Video\Decoder;

class NvidiaCudaDecoder extends AbstractDecoder
{
    public function name(): string
    {
        return 'nvidia-cuda';
    }
}
