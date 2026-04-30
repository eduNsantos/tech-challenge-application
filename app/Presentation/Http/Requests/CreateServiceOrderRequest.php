<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id'          => 'required|uuid',
            'services'               => 'array|required|min:1',
            'services.*.service_id'  => 'required|uuid',
            'services.*.quantity'    => 'required|numeric|min:0.01',
            'parts'                  => 'sometimes|array',
            'parts.*.item_id'        => 'required_with:parts|uuid',
            'parts.*.quantity'       => 'required_with:parts|numeric|min:0.01',
            'send_quote'             => 'sometimes|boolean',
        ];
    }
}
