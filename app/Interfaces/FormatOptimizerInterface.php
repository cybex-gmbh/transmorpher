<?php

namespace App\Interfaces;

interface FormatOptimizerInterface
{
    public function optimize(string $pathToImage, ?int $quality = null): void;
}
