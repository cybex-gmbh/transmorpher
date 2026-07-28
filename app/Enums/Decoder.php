<?php

namespace App\Enums;

enum Decoder: string
{
    //
    case CPU = 'cpu';
    case NVIDIA_CUDA = 'nvidia-cuda';

    public function getInitialParameters(): array
    {
        return config(sprintf('transmorpher.classes.video.decoder.%s', $this->value), []);
    }
}
