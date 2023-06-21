<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\UploadState;
use App\Http\Requests\ImageUploadSlotRequest;
use App\Http\Requests\UploadRequest;
use App\Http\Requests\VideoUploadSlotRequest;
use App\Models\UploadSlot;
use App\Models\User;
use File;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class UploadSlotController extends Controller
{
    /**
     * @param UploadRequest $request
     * @param UploadSlot $uploadSlot
     * @return JsonResponse|UploadedFile
     * @throws UploadFailedException
     * @throws UploadMissingFileException
     */
    public function receiveFile(UploadRequest $request, UploadSlot $uploadSlot): JsonResponse|UploadedFile
    {
        $receiver = new FileReceiver($request->file('file'), $request, HandlerFactory::classFromRequest($request));

        // Check if the chunk is successfully uploaded.
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $save = $receiver->receive();

        // Check if the full upload has finished and if so, save the file.
        if ($save->isFinished()) {
            return $this->saveFile($save->getFile(), $uploadSlot, $uploadSlot->media_type);
        }

        // Full file is not yet uploaded, send the current progress.
        return response()->json([
            "done" => $save->handler()->getPercentageDone(),
        ]);
    }

    /**
     * Handle the incoming request.
     *
     * @param ImageUploadSlotRequest $request
     *
     * @return JsonResponse
     */
    public function reserveImageUploadSlot(ImageUploadSlotRequest $request): JsonResponse
    {
        return $this->updateOrCreateUploadSlot($request->user(), $request->merge(['media_type' => MediaType::IMAGE->value])->all());
    }

    /**
     * Handle the incoming request.
     *
     * @param VideoUploadSlotRequest $request
     *
     * @return JsonResponse
     */
    public function reserveVideoUploadSlot(VideoUploadSlotRequest $request): JsonResponse
    {
        return $this->updateOrCreateUploadSlot($request->user(), $request->merge(['media_type' => MediaType::VIDEO->value])->all());
    }

    /**
     * @param UploadedFile $uploadedFile
     * @param UploadSlot $uploadSlot
     * @param MediaType $type
     *
     * @return JsonResponse
     */
    protected function saveFile(UploadedFile $uploadedFile, UploadSlot $uploadSlot, MediaType $type): JsonResponse
    {
        $user = $uploadSlot->User;
        $identifier = $uploadSlot->identifier;

        $media = $user->Media()->firstOrNew(['identifier' => $identifier, 'type' => $type]);
        $media->validateUploadFile($uploadedFile, $type->handler()->getValidationRules(), $uploadSlot);
        $media->save();

        $versionNumber = $media->Versions()->max('number') + 1;
        $basePath = FilePathHelper::toBaseDirectory($user, $identifier);
        $fileName = FilePathHelper::createOriginalFileName($versionNumber, $uploadedFile->getClientOriginalName());
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();

        if ($filePath = $originalsDisk->putFileAs($basePath, $uploadedFile, $fileName)) {
            $version = $media->Versions()->create(['number' => $versionNumber, 'filename' => $fileName]);
            $responseState = $type->handler()->handleSavedFile($basePath, $uploadSlot, $filePath, $media, $version);
        } else {
            $responseState = ResponseState::WRITE_FAILED;
        }

        if ($responseState->getState() === UploadState::ERROR) {
            $versionNumber -= 1;
            $originalsDisk->delete($filePath);
            $version?->delete();
        }

        // Delete local file.
        File::delete($uploadedFile);

        return response()->json([
            'state' => $responseState->getState()->value,
            'response' => $responseState->getResponse(),
            'identifier' => $media->identifier,
            'version' => $versionNumber,
            'client' => $user->name,
            // Base path is only passed for images since the video is not available at this path yet.
            'public_path' => $type === MediaType::IMAGE ? $basePath : null,
            'upload_token' => $uploadSlot->token
        ], 201);
    }

    protected function updateOrCreateUploadSlot(User $user, array $requestData): JsonResponse
    {
        // Token and valid_until will be set in the 'saving' event.
        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(['identifier' => $requestData['identifier']], $requestData);

        return response()->json([
            'state' => ResponseState::UPLOAD_SLOT_CREATED->getState()->value,
            'response' => ResponseState::UPLOAD_SLOT_CREATED->getResponse(),
            'identifier' => $requestData['identifier'],
            'upload_token' => $uploadSlot->token
        ]);
    }
}
