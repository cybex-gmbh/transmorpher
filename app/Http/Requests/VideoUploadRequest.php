<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VideoUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->tokenCan('transmorpher:upload-video');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Mimetypes to mimes:
     *      video/x-msvideo	=> avi
     *      video/mpeg      => mpeg mpg mpe m1v m2v
     *      video/ogg		=> ogv
     *      video/webm		=> webm
     *      video/mp4		=> mp4 mp4v mpg4
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'upload_token' => ['required', 'string'],
            'video' => [
                'required',
                'mimetypes:video/x-msvideo,video/mpeg,video/ogg,video/webm,video/mp4',
            ],
        ];
    }
}
