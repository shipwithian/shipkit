<?php

namespace App\Http\Requests\AccessControl;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The search box of an access-control list page. A page with filters of its own extends it.
 */
class ListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The search text, trimmed, or null when the box is empty.
     */
    public function search(): ?string
    {
        $search = trim((string) $this->string('search'));

        return $search === '' ? null : $search;
    }
}
