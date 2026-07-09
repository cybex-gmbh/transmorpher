<?php

namespace App\Http\Requests\V2;

use App\Facades\UploaderFacade as Uploader;
use Illuminate\Foundation\Http\FormRequest;

class CompleteUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // The request is authorized by an upload token, which is previously retrieved from a Sanctum protected route.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * Merges the uploader-specific completion validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return Uploader::getCompletionValidationRules();
    }
}


