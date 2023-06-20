<?php

namespace App\Http\Requests;

use App\Helpers\ValidationRegex;
use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends FormRequest
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
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if (!preg_match(ValidationRegex::forIdentifier(), pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME))) {
                        $fail(trans('responses.file_name_invalid'));
                    }
                }
            ]
        ];
    }
}
