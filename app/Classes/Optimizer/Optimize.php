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
     * @param string $derivative
     * @param int|null $quality
     * @return string
     * @throws Exception
     */
    public function optimize(string $derivative, int $quality = null): string
    {
        $tempFile = $this->getTemporaryFile($derivative);

        // Optimizes the image based on optimizers configured in 'config/image-optimizer.php'.
        ImageFormat::fromMimeType(mime_content_type($tempFile))->getOptimizer()->optimize($tempFile, $quality);

        $derivative = file_get_contents($tempFile);

        if ($derivative === false) {
            unlink($tempFile);

            throw new Exception('Failed to read the optimized image.');
        }

        return $derivative;
    }

    /**
     * @param string $derivative
     * @return string
     * @throws Exception
     */
    public function removeDocumentMetadata(string $derivative): string
    {
        $tempFile = $this->getTemporaryFile($derivative);
        $pdfMerge = new PdfMerge();

        try {
            $pdfMerge->add($tempFile);
            $pdfData = $pdfMerge->merge('', 'S');
        } catch (Exception $exception) {
            unlink($tempFile);

            throw $exception;
        }

        return $pdfData;
    }

    /**
     * @param string $derivative
     * @return false|string
     */
    protected function getTemporaryFile(string $derivative): string|false
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'transmorpher');
        file_put_contents($tempFile, $derivative);

        return $tempFile;
    }
}
