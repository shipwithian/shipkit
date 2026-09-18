<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class ResetPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the request for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has(Fortify::email())) {
            $this->merge([
                Fortify::email() => Str::lower((string) $this->input(Fortify::email())),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            Fortify::email() => ['required', 'email'],
            'password' => $this->passwordRules(),
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
