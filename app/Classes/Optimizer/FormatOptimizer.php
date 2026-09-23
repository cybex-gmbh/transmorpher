<?php

namespace App\Classes\Optimizer;

use App\Interfaces\FormatOptimizerInterface;
use ImageOptimizer;

class FormatOptimizer implements FormatOptimizerInterface
{
    public function optimize(string $pathToImage, ?int $quality = null): void
    {
        ImageOptimizer::optimize($pathToImage);
    }
}
