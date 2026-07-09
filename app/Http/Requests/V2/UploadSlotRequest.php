<?php

namespace App\Http\Requests\V2;

use App\Enums\ValidationRegex;
use Illuminate\Foundation\Http\FormRequest;

class UploadSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->tokenCan('transmorpher:reserve-upload-slot');
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
            'filename' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Using these characters might cause file system problems.
                    $disallowedCharacters = ['\'', '/', '\\', ':', '?', '"', '<', '>', '|', '*'];
                    $fileName = pathinfo($value, PATHINFO_FILENAME);

                    if (preg_match(sprintf('/[%s]/', preg_quote(implode($disallowedCharacters), '/')), $fileName)) {
                        $fail(trans('responses.file_name_invalid', ['disallowedCharacters' => implode(', ', $disallowedCharacters)]));
                    }

                    if (!trim($fileName)) {
                        $fail(trans('responses.file_name_invalid_only_spaces'));
                    }
                },
            ],
        ];
    }
}

