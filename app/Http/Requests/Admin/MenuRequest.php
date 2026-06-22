<?php

namespace App\Http\Requests\Admin;

use App\Services\AktaMenuService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Proteksi privilege escalation: hanya admin
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $menuId = $this->route('menu');

        return [
            'label'      => ['required', 'string', 'max:100'],
            'code'       => ['required', 'string', 'max:10'],
            'route_name' => [
                'nullable', 'string', 'max:150',
                Rule::unique('menus', 'route_name')->ignore($menuId),
            ],
            'path'       => ['nullable', 'string', 'max:200'],
            'icon'       => ['nullable', 'string', 'max:50'],
            'parent_id'  => ['nullable', 'integer', 'exists:menus,id'],
            'order'      => ['required', 'integer', 'min:1', 'max:999'],
            'is_active'  => ['required', 'boolean'],
            // Roles yang boleh melihat menu ini
            'roles'      => ['required', 'array', 'min:1'],
            'roles.*'    => ['required', 'string', Rule::in(AktaMenuService::ROLES)],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.*.in' => 'Role tidak valid. Pilih dari: ' . implode(', ', AktaMenuService::ROLES),
        ];
    }
}
