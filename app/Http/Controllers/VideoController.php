<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Helpers\Upload;
use App\Http\Requests\UploadRequest;
use App\Models\UploadSlot;
use Illuminate\Http\JsonResponse;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;

class VideoController extends Controller
{
    /**
     * Handles incoming image upload requests.
     *
     * @param UploadRequest $request
     * @param UploadSlot    $uploadSlot
     *
     * @return JsonResponse
     * @throws UploadFailedException
     * @throws UploadMissingFileException
     */
    public function receiveFile(UploadRequest $request, UploadSlot $uploadSlot): JsonResponse
    {
        if (($response = Upload::receive($request)) instanceof JsonResponse) {
            return $response;
        }

        return Upload::saveFile($response, $uploadSlot, MediaType::VIDEO);
    }
}
