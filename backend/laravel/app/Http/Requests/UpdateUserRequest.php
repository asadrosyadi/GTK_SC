<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('edit_user');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'iot_id' => 'nullable|string|max:255',
            'iot_token' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'role' => ['required'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('field.name'),
            'role' => __('field.role'),
        ];
    }
}
