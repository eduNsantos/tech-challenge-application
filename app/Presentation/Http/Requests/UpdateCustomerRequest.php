<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'email' => 'email|unique:customers',
            'phone' => 'string|max:255',
            'document' => 'string|max:14'
        ];
    }
}