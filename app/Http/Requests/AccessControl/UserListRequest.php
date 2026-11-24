<?php

namespace App\Http\Requests\AccessControl;

use Illuminate\Validation\Rule;

/**
 * The users page's search box and role filter.
 */
class UserListRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'role' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }

    public function roleId(): ?int
    {
        return $this->filled('role') ? $this->integer('role') : null;
    }
}
