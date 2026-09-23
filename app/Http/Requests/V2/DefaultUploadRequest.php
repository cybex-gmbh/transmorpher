<?php

namespace App\Http\Requests\V2;

use App\Enums\ValidationRegex;
use Illuminate\Foundation\Http\FormRequest;

class DefaultUploadRequest extends FormRequest
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
            ],
            'identifier' => [
                'required',
                'string',
                sprintf('regex:%s', ValidationRegex::IDENTIFIER->get()),
                function ($attribute, $value, $fail) {
                    if (strtolower($this->uploadSlot->identifier) !== strtolower($value)) {
                        $fail(trans('responses.non_matching_identifier'));
                    }
                }
            ]
        ];
    }
}

