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
     * Transform an image based on specified transformations.
     *
     * @param string $pathToOriginalImage
     * @param array|null $transformations
     *
     * @return string Binary string of the image.
     */
    public function image(string $pathToOriginalImage, ?array $transformations = null): string
    {
        $fileData = $this->getOriginalFileData($pathToOriginalImage);

        if (!$transformations) {
            return $fileData;
        }

        return $this->applyTransformations($fileData, $transformations);
    }

    /**
     * Transform a document based on specified transformations.
     * Will first create an image from the document.
     *
     * @param string $pathToOriginalDocument
     * @param array|null $transformations
     *
     * @return string Binary string of the image.
     */
    public function document(string $pathToOriginalDocument, ?array $transformations = null): string
    {
        $fileData = $this->getOriginalFileData($pathToOriginalDocument);

        if (!$transformations) {
            return $fileData;
        }

        $imageData = $this->pdfToImage($fileData, $transformations);

        return $this->applyTransformations($imageData, $transformations);
    }

    /**
     * @param string $fileData
     * @param array|null $transformations
     *
     * @return string Binary string of the image.
     *
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

        return $imagick->getImageBlob();
    }

    /**
     * @throws FileNotFoundException
     */
    protected function getOriginalFileData(string $path): string
    {
        $disk = MediaStorage::ORIGINALS->getDisk();

        if (!$disk->exists($path)) {
            throw new FileNotFoundException(sprintf('File not found at path "%s" on configured disk', $path));
        }

        return $disk->get($path);
    }

    /**
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
     *
     * @throws ImagickPolicyException|DocumentPageDoesNotExistException|ImagickException|ImageTransformationException
     */
    protected function handleReadImagickExceptions(ImagickException $exception, ?array $transformations = null): void
    {
        // A policy denies access to a delegate, coder, filter, path, or resource.
        if ($exception->getCode() === 499) {
            $customException = new ImagickPolicyException($exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        $requestedPage = $transformations[Transformation::PAGE->value] ?? false;

        // The call to a delegate failed (in this case it should be ghostscript, which processes the PDF)
        if ($exception->getCode() === 415 && $requestedPage) {
            // We assume an error happened because the requested page does not exist. In case this is not applicable, check the error logs.
            $customException = new DocumentPageDoesNotExistException($requestedPage, $exception->getCode(), previous: $exception);
        }

        report($customException ?? $exception);
        throw $customException ?? $exception;
    }
}
