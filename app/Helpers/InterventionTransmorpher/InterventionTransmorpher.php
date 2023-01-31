<?php

namespace App\Helpers\InterventionTransmorpher;

use App\Interfaces\TransmorpherInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Image;
use Intervention\Image\Exception\NotSupportedException;
use Storage;

class InterventionTransmorpher implements TransmorpherInterface
{
    /**
     * @param string     $pathToOriginalImage
     * @param array|null $transformations
     *
     * @return string
     * @throws FileNotFoundException
     */
    public function transmorph(string $pathToOriginalImage, array $transformations = null): string
    {
        $imageData = Storage::disk(config('transmorpher.disks.originals'))->get($pathToOriginalImage);

        if (!$imageData) {
            throw new FileNotFoundException(sprintf('File not found at path "%s" on configured disk', $pathToOriginalImage));
        }

        $image = \Intervention\Image\Facades\Image::make($imageData);

        if (!$transformations) {
            return $imageData;
        }

        $width   = $transformations['w'] ?? $image->getWidth();
        $height  = $transformations['h'] ?? $image->getHeight();
        $format  = $transformations['f'] ?? null;
        $quality = $transformations['q'] ?? null;

        $image = $this->resize($image, $width, $height);

        if ($format) {
            return $this->format($image->stream(), $format, $quality);
        }

        return $image->stream();
    }

    /**
     * @param     $image
     * @param int $width
     * @param int $height
     */
    public function resize($image, int $width, int $height)
    {
        return $image->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
    }

    /**
     * @param          $image
     * @param string   $format
     * @param int|null $quality
     *
     * @return Image|string
     */
    public function format($image, string $format, int $quality = null): Image|string
    {
        return match ($format) {
            'jpg' => config('transmorpher.converters.jpg')::encode($image, $format, $quality),
            'png' => config('transmorpher.converters.png')::encode($image, $format, $quality),
            'gif' => config('transmorpher.converters.gif')::encode($image, $format, $quality),
            'webp' => config('transmorpher.converters.webp')::encode($image, $format, $quality),
            default => throw new NotSupportedException(sprintf('Format %s not supported by %s', $format, $this::class)),
        };
    }

    /**
     * @return string[]
     */
    public function getSupportedFormats(): array
    {
        return [
            'jpg',
            'png',
            'gif',
            'webp',
        ];
    }
}
