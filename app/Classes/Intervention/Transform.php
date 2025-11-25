<?php

namespace App\Classes\Intervention;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Interfaces\ConvertedImageInterface;
use App\Exceptions\DocumentPageDoesNotExistException;
use App\Interfaces\TransformInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use InterventionImage;
use Imagick;
use ImagickException;
use Log;

class Transform implements TransformInterface
{
    /**
     * Transform image based on specified transformations.
     *
     * @param string $pathToOriginalImage
     * @param array|null $transformations
     *
     * @return string Binary string of the image.
     */
    public function transform(string $pathToOriginalImage, ?array $transformations = null): string
    {
        $fileHandle = $this->getOriginalFileStream($pathToOriginalImage);
        $mimeType = mime_content_type($fileHandle);
        $fileData = stream_get_contents($fileHandle);

        if (!$transformations) {
            return $fileData;
        }

        return match ($mimeType) {
            'application/pdf' => $this->pdfToImage($fileData, $transformations),
            default => $this->applyTransformations($fileData, $transformations),
        };
    }

    /**
     * @param string $fileData
     * @param array|null $transformations
     * @return string
     * @throws DocumentPageDoesNotExistException
     */
    protected function pdfToImage(string $fileData, ?array $transformations = null): string
    {
        // We need a local file for Imagick to be able to access only the requested page.
        $tempFile = tempnam(sys_get_temp_dir(), 'transmorpher');
        file_put_contents($tempFile, $fileData);

        $ppi = $transformations[Transformation::PPI->value] ?? config('transmorpher.document_default_ppi');
        $imagick = new Imagick();
        $imagick->setResolution($ppi, $ppi);

        try {
            $imagick->readImage(sprintf('%s[%d]', $tempFile, ($transformations[Transformation::PAGE->value] ?? 1) - 1));
        } catch (ImagickException $exception) {
            Log::error($exception->getMessage());

            // Assuming an error happened because the requested page does not exist, we throw a custom exception.
            throw new DocumentPageDoesNotExistException($transformations[Transformation::PAGE->value]);
        } finally {
            unlink($tempFile);
        }

        $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $imagick->setImageFormat($transformations[Transformation::FORMAT->value]);

        return $this->applyTransformations($imagick->getImageBlob(), $transformations);
    }

    /**
     * @param string $path
     * @return resource|null
     * @throws FileNotFoundException
     */
    protected function getOriginalFileStream(string $path)
    {
        $disk = MediaStorage::ORIGINALS->getDisk();

        if (!$disk->exists($path)) {
            throw new FileNotFoundException(sprintf('File not found at path "%s" on configured disk', $path));
        }

        return $disk->readStream($path);
    }

    /**
     * @param string $imageData
     * @param array|null $transformations
     * @return string
     */
    protected function applyTransformations(string $imageData, ?array $transformations = null): string
    {
        $image = InterventionImage::make($imageData);

        $width   = $transformations[Transformation::WIDTH->value] ?? $image->getWidth();
        $height  = $transformations[Transformation::HEIGHT->value] ?? $image->getHeight();
        $format  = $transformations[Transformation::FORMAT->value] ?? null;
        $quality = $transformations[Transformation::QUALITY->value] ?? null;

        $image = $this->resize($image, $width, $height);

        if ($format) {
            return $this->format($image->stream(), $format, $quality)->getBinary();
        }

        return $image->stream(null, $quality);
    }

    /**
     * Resize an image based on specified width and height.
     * Keeps the aspect ratio and prevents upsizing.
     *
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
     * Use a converter class to encode the image to given format and quality.
     *
     * @param          $image
     * @param string   $format
     * @param int|null $quality
     *
     * @return ConvertedImageInterface
     */
    public function format($image, string $format, ?int $quality = null): ConvertedImageInterface
    {
        return ImageFormat::from($format)->getConverter()->encode($image, $format, $quality);
    }
}
