<?php

namespace App\Http\Requests\V2;

use Illuminate\Foundation\Http\FormRequest;
use UploadHandler;

class GetChunkUrlRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->tokenCan('transmorpher:upload.url');
    }

    /**
     * Get the validation rules that apply to the request.
     * Applies the upload-handler-specific chunk url validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return UploadHandler::getChunkUrlRequestValidationRules();
    }
}
