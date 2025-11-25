<?php

namespace App\Classes\Intervention;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Exceptions\DocumentPageDoesNotExistException;
use App\Exceptions\ImageTransformationException;
use App\Exceptions\ImagickPolicyException;
use App\Interfaces\TransformInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Imagick;
use ImagickException;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Laravel\Facades\Image as ImageManager;

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
     * @throws ImageTransformationException
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
            $this->handleReadImagickExceptions($exception, $transformations);
        } finally {
            unlink($tempFile);
        }

        $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        try {
            $imagick->setImageFormat($transformations[Transformation::FORMAT->value]);
        } catch (ImagickException $exception) {
            $customException = new ImageTransformationException($exception->getMessage(), $exception->getCode(), previous: $exception);

            report($customException);
            throw $customException;
        }

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
     * @throws ImageTransformationException
     */
    protected function applyTransformations(string $imageData, ?array $transformations = null): string
    {
        try {
            $image = ImageManager::read($imageData);
        } catch (DecoderException $exception) {
            $customException = new ImageTransformationException($exception->getMessage(), 420, previous: $exception);

            report($customException);
            throw $customException;
        }

        $width = $transformations[Transformation::WIDTH->value] ?? $image->width();
        $height = $transformations[Transformation::HEIGHT->value] ?? $image->height();
        $format = $transformations[Transformation::FORMAT->value] ?? null;
        $quality = $transformations[Transformation::QUALITY->value] ?? 100;

        $image = $image->scaleDown($width, $height);

        if ($format) {
            return ImageFormat::from($format)->getConverter()->encode($image, $format, $quality)->getBinary();
        }

        return $image->encode(new AutoEncoder(quality: $quality))->toString();
    }

    /**
     * See https://imagemagick.org/script/exception.php for error code reference.
     * @throws ImagickPolicyException|DocumentPageDoesNotExistException|ImagickException|ImageTransformationException
     */
    protected function handleReadImagickExceptions(ImagickException $exception, ?array $transformations = null): void
    {
        // A policy denies access to a delegate, coder, filter, path, or resource.
        if ($exception->getCode() === 499) {
            $customException = new ImagickPolicyException($exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        $requestedPage = $transformations[Transformation::PAGE->value] ?? false;
        if ($exception->getCode() === 1 && $requestedPage) {
            // We assume an error happened because the requested page does not exist. In case this is not applicable, check the error logs.
            $customException = new DocumentPageDoesNotExistException($requestedPage, $exception->getCode(), previous: $exception);
        }

        report($customException ?? $exception);
        throw $customException ?? $exception;
    }
}
