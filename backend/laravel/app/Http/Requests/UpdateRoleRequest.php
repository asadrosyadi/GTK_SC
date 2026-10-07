<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('edit_role');
    }

    public function rules(): array
    {
        return [
            'role' => ['required'],
            'permission' => ['array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'role' => __('field.role'),
            'permission' => __('field.permission'),
        ];
    }
}
