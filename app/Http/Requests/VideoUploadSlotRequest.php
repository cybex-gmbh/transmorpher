<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VideoUploadSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->tokenCan('transmorpher:reserve-video-upload-slot');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // Only allow lower/uppercase characters, numbers, underscores and dashes.
            // The first character can't be a dash.
            'identifier' => ['required', 'string', 'regex:/^[\w][\w\-]*$/'],
            'callback_url' => ['required', 'string', 'url']
        ];
    }
}
