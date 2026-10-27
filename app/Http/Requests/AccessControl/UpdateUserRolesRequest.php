<?php

namespace App\Http\Requests\AccessControl;

use App\Domain\AccessControl\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRolesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => [
                'integer',
                'distinct',
                Rule::exists((new Role)->getTable(), 'id')->where('guard_name', 'web'),
            ],
        ];
    }
}
