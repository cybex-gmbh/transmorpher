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
     * @param string $fileData
     * @param int|null $quality
     * @return string
     * @throws Exception
     */
    public function optimize(string $fileData, int $quality = null): string
    {
        $tempFile = $this->getTemporaryFile($fileData);

        // Optimizes the image based on optimizers configured in 'config/image-optimizer.php'.
        ImageFormat::fromMimeType(mime_content_type($tempFile))->getOptimizer()->optimize($tempFile, $quality);

        $fileData = file_get_contents($tempFile);

        if ($fileData === false) {
            unlink($tempFile);

            throw new Exception('Failed to read the optimized image.');
        }

        return $fileData;
    }

    /**
     * @param string $fileData
     * @return string
     * @throws Exception
     */
    public function removeDocumentMetadata(string $fileData): string
    {
        if (!config('transmorpher.document_remove_metadata')) {
            return $fileData;
        }

        $tempFile = $this->getTemporaryFile($fileData);
        $pdfMerge = new PdfMerge();

        try {
            $pdfMerge->add($tempFile);
            $pdfData = $pdfMerge->merge('', 'S');
        } catch (Exception $exception) {
            throw $exception;
        } finally {
            unlink($tempFile);
        }

        return $pdfData;
    }

    /**
     * @param string $fileData
     * @return false|string
     */
    protected function getTemporaryFile(string $fileData): string|false
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'transmorpher');
        file_put_contents($tempFile, $fileData);

        return $tempFile;
    }
}
