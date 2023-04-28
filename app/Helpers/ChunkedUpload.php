<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class ChunkedUpload
{
    /**
     * @param Request $request
     *
     * @return JsonResponse|UploadedFile
     * @throws UploadFailedException
     * @throws UploadMissingFileException
     */
    public static function receive(Request $request): JsonResponse|UploadedFile
    {
        $receiver = new FileReceiver($request->file('file'), $request, HandlerFactory::classFromRequest($request));

        // Check if the chunk is successfully uploaded.
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $save = $receiver->receive();

        // Check if the full upload has finished and if so, return the file.
        if ($save->isFinished()) {
            return $save->getFile();
        }

        // Full file is not yet uploaded, send the current progress.
        return response()->json([
            "done" => $save->handler()->getPercentageDone(),
        ]);
    }
}
