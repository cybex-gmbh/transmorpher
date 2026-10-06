<?php

namespace App\Classes\Optimizer;

use App\Enums\ImageFormat;
use Exception;
use Karriere\PdfMerge\PdfMerge;

class Optimize
{
    /**
     * Optimize an image derivative.
     * Creates a temporary file since image optimizers only work locally.
     *
     * @param string $imageData
     * @param int|null $quality
     *
     * @return string
     *
     * @throws Exception
     */
    public function image(string $imageData, ?int $quality = null): string
    {
        $tempImageFilePath = $this->createTemporaryFile($imageData);

        try {
            // Optimizes the image based on optimizers configured in 'config/image-optimizer.php'.
            ImageFormat::fromMimeType(mime_content_type($tempImageFilePath))->getOptimizer()->optimize($tempImageFilePath, $quality);
            $optimizedImageData = file_get_contents($tempImageFilePath);
        } finally {
            unlink($tempImageFilePath);
        }

        if ($optimizedImageData === false) {
            throw new Exception('Failed to read the optimized image.');
        }

        return $optimizedImageData;
    }

    /**
     * Optimize a document derivative. Currently only removes metadata if enabled.
     * Creates a temporary file since removing metadata only works locally.
     *
     * @param string $documentData
     * @return string
     *
     * @throws Exception
     */
    public function document(string $documentData): string
    {
        if (!config('transmorpher.media.document.metadata.remove')) {
            return $documentData;
        }

        $tempDocumentFilePath = $this->createTemporaryFile($documentData);
        $pdfMerge = new PdfMerge();

        try {
            $pdfMerge->add($tempDocumentFilePath);
            $pdfData = $pdfMerge->merge('', 'S');
        } finally {
            unlink($tempDocumentFilePath);
        }

        return $pdfData;
    }

    protected function createTemporaryFile(string $fileData): string|false
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'transmorpher');
        file_put_contents($tempFile, $fileData);

        return $tempFile;
    }
}
