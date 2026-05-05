<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
        /** @var Role $role */
        $role = $this->route('role');
        \assert($role instanceof Role);

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'slug')->ignore($role->id)],
            'permission_slugs' => ['sometimes', 'array', 'min:1'],
            'permission_slugs.*' => ['required', 'string', 'max:64', 'exists:permissions,slug'],
        ];
    }
}
