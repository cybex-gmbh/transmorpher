<?php

namespace App\Enums;

enum Decoder: string
{
    //
    case CPU = 'cpu';
    case NVIDIA_CUDA = 'nvidia_cuda';

    public function getInitialParameters(): array
    {
        return config(sprintf('decoder.%s', $this->value)) ?? [];
    }
}
