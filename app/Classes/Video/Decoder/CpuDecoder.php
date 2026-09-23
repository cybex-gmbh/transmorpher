<?php

namespace App\Classes\Video\Decoder;

class CpuDecoder extends AbstractDecoder
{
    public function name(): string
    {
        return 'cpu';
    }
}
