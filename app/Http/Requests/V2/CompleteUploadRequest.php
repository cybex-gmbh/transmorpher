<?php

namespace App\Http\Requests\V2;

use Illuminate\Foundation\Http\FormRequest;
use UploadHandler;

class CompleteUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->tokenCan('transmorpher:upload.complete');
    }

    /**
     * Get the validation rules that apply to the request.
     * Applies the upload-handler-specific complete validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return UploadHandler::getCompleteRequestValidationRules();
    }
}
