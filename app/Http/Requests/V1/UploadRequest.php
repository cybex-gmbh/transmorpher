<?php

namespace App\Http\Requests\V1;

use App\Enums\ValidationRegex;
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
                // Check file name for disallowed characters and reject if present.
                function ($attribute, $value, $fail) {
                    // Custom rules are evaluated first, so we can't use Laravel's existing file validation.
                    if (!$this->hasFile($attribute)) {
                        $fail(trans('validation.file', ['attribute' => $attribute]));
                        return;
                    }

                    // Using these characters might cause file system problems.
                    $disallowedCharacters = ['\'', '/', '\\', ':', '?', '"', '<', '>', '|', '*'];
                    $fileName = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);

                    if (preg_match(sprintf('/[%s]/', preg_quote(implode($disallowedCharacters), '/')), $fileName)) {
                        $fail(trans('responses.file_name_invalid', ['disallowedCharacters' => implode(', ', $disallowedCharacters)]));
                    }

                    // Check if file name only consists of spaces.
                    if (!trim($fileName)) {
                        $fail(trans('responses.file_name_invalid_only_spaces'));
                    }
                },
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
