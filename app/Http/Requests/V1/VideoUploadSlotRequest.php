<?php

namespace App\Http\Requests\V1;

use App\Enums\ValidationRegex;
use Illuminate\Foundation\Http\FormRequest;

class VideoUploadSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->tokenCan('transmorpher:reserve-video-upload-slot');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Identifier is used in file paths and URLs, therefore only lower/uppercase characters, numbers, underscores and hyphens are allowed.
            'identifier' => ['required', 'string', sprintf('regex:%s', ValidationRegex::IDENTIFIER->get())],
            'callback_url' => ['required', 'string', 'url']
        ];
    }
}
