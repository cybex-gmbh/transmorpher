<?php

namespace App\Http\Requests;

use Carbon\Carbon;
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
        return ['upload_token' => [
            function (string $attribute, mixed $value, Closure $fail) {
                $uploadToken = $this->route('upload_token');

                if (Carbon::now()->isAfter($uploadToken->valid_until)) {
                    $uploadToken->delete();
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
        $this->merge(['upload_token' => $this->upload_token]);
    }
}
