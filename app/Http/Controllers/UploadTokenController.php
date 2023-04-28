<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageUploadTokenRequest;
use App\Http\Requests\VideoUploadTokenRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadTokenController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param ImageUploadTokenRequest $request
     * @return JsonResponse
     */
    public function getImageToken(ImageUploadTokenRequest $request): JsonResponse
    {
        return $this->createUploadToken($request);
    }

    /**
     * Handle the incoming request.
     *
     * @param VideoUploadTokenRequest $request
     * @return JsonResponse
     */
    public function getVideoToken(VideoUploadTokenRequest $request): JsonResponse
    {
        return $this->createUploadToken($request);
    }

    protected function createUploadToken(Request $request): JsonResponse
    {
        $token = uniqid();

        $request->user()->UploadTokens()->create([
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
