<?php

namespace App\Interfaces;

interface ConverterInterface
{
    public function encode(string $image, string $format, int $quality = null);
}
