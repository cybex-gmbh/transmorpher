<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageUploadSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->tokenCan('transmorpher:reserve-image-upload-slot');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // Identifier is used in file paths and URLs, therefore only lower/uppercase characters, numbers, underscores and dashes are allowed.
            'identifier' => ['required', 'string', 'regex:/^[\w][\w\-]*$/'],
        ];
    }
}
