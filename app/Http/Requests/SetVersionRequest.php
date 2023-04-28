<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->tokenCan('transmorpher:set-version');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'callback_token' => ['nullable', 'string'],
            'callback_url' => ['nullable', 'string', 'url']
        ];
    }
}
