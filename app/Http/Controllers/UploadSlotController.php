<?php

namespace App\Http\Controllers;

use App\Enums\ResponseState;
use App\Helpers\Upload;
use App\Http\Requests\ImageUploadSlotRequest;
use App\Http\Requests\VideoUploadSlotRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadSlotController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param ImageUploadSlotRequest $request
     *
     * @return JsonResponse
     */
    public function reserveImageUploadSlot(ImageUploadSlotRequest $request): JsonResponse
    {
        return $this->createUploadSlot($request);
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
        return $this->createUploadSlot($request);
    }

    protected function createUploadSlot(Request $request): JsonResponse
    {
        $uploadSlot = Upload::createUploadSlot($request->user(), $request->input('identifier'), $request->input('callback_url'), $request->input('validation_rules'));

        return response()->json([
            'success' => ResponseState::UPLOAD_SLOT_CREATED->success(),
            'response' => ResponseState::UPLOAD_SLOT_CREATED->value,
            'identifier' => $request->input('identifier'),
            'upload_token' => $uploadSlot->token
        ]);
    }
}
