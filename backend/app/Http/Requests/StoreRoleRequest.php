<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,slug'],
            'name' => ['required', 'string', 'max:120'],
            'permission_slugs' => ['required', 'array', 'min:1'],
            'permission_slugs.*' => ['required', 'string', 'max:64', 'exists:permissions,slug'],
        ];
    }
}
