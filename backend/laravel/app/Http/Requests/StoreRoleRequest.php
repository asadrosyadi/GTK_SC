<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create_role');
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
