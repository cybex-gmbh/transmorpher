<?php

namespace App\Http\Requests;

use Closure;
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
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['upload_slot' => [
            function (string $attribute, mixed $value, Closure $fail) {
                $uploadSlot = $this->route('upload_slot');

                if (!$uploadSlot->isValid) {
                    $uploadSlot->delete();
                    $fail("The upload token is no longer valid");
                }
            },
        ]];
    }

    /**
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['upload_slot' => $this->upload_slot]);
    }
}
