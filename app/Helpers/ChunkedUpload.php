<?php

namespace App\Helpers;

use App\Enums\MediaType;
use App\Http\Requests\UploadRequest;
use App\Models\UploadToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class ChunkedUpload
{
    /**
     * @throws UploadMissingFileException
     * @throws UploadFailedException
     */
    public static function receive(UploadRequest $request, MediaType $mediaType): JsonResponse|UploadedFile
    {
        // create the file receiver
        $receiver = new FileReceiver($request->file($mediaType->value), $request, HandlerFactory::classFromRequest($request));

        // check if the upload is success, throw exception or return response you need
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $save = $receiver->receive();

        // check if the upload has finished (in chunk mode it will send smaller files)
        if ($save->isFinished()) {
            // save the file and return any response you need, current example uses `move` function. If you are
            // not using move, you need to manually delete the file by unlink($save->getFile()->getPathname())
            return $save->getFile();
        }

        // we are in chunk mode, lets send the current progress
        $handler = $save->handler();

        return response()->json([
            "done" => $handler->getPercentageDone(),
        ]);

    }

    public static function checkToken(?UploadToken $uploadToken): bool|JsonResponse
    {
        if (!$uploadToken) {
            return response()->json([
                'success' => false,
                'response' => 'The upload token is not valid.',
            ], 401);
        }

        if (Carbon::now()->isAfter($uploadToken->valid_until)) {
            $uploadToken->delete();

            return response()->json([
                'success' => false,
                'response' => 'The upload token is no longer valid.',
            ], 410);
        }

        return true;
    }
}