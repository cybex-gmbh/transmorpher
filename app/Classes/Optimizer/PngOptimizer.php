<?php

namespace App\Classes\Optimizer;

use Config;
use Spatie\ImageOptimizer\Optimizers\Pngquant;

class PngOptimizer extends FormatOptimizer
{
    public function optimize(string $pathToImage, int $quality = null): void
    {
        if ($quality) {
            Config::set(
                sprintf('image-optimizer.optimizers.%s', Pngquant::class),
                array_merge(config(sprintf('image-optimizer.optimizers.%s', Pngquant::class)), [sprintf('--quality %1$s-%1$s', $quality)])
            );
        }

        parent::optimize($pathToImage);
    }
}
