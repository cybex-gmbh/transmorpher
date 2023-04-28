<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageUploadSlotRequest;
use App\Http\Requests\VideoUploadSlotRequest;
use Carbon\Carbon;
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
    public function getImageToken(ImageUploadSlotRequest $request): JsonResponse
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
    public function getVideoToken(VideoUploadSlotRequest $request): JsonResponse
    {
        return $this->createUploadSlot($request);
    }

    protected function createUploadSlot(Request $request): JsonResponse
    {
        $token = uniqid();

        $request->user()->UploadSlots()->create([
            'token' => $token,
            'identifier' => $request->input('identifier'),
            'callback_token' => $request->input('callback_token') ?? null,
            'callback_url' => $request->input('callback_url') ?? null,
            'validation_rules' => $request->input('validation_rules') ?? null,
            'valid_until' => Carbon::now()->addHours(24)
        ]);

        return response()->json([
            'success' => true,
            'response' => 'Successfully created upload token.',
            'identifier' => $request->input('identifier'),
            'upload_token' => $token
        ]);
    }
}
