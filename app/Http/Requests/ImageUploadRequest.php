<?php

namespace App\Http\Requests;

use App\Enums\ImageFormat;
use Illuminate\Foundation\Http\FormRequest;

class ImageUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // TODO: Consolidate with ImageUploadRequest?
        return [
            'upload_token' => ['required', 'string'],
        ];
    }
}
