<?php

namespace App\Interfaces;

interface TransmorpherInterface
{
        public function transmorph(string $pathToOriginalImage, array $transformations = null): string;
        public function resize($image, int $width, int $height);
        public function format($image, string $format, int $quality = null);
        public function getSupportedFormats(): array;
}
