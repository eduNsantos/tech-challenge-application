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
            'vehicle_brand' => 'required|string|max:255',
            'vehicle_model' => 'required|string|max:255',
            'vehicle_year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'vehicle_plate' => 'required|string|max:10',
            'services' => 'required|array|min:1',
            'services.*.name' => 'required|string|max:255',
            'services.*.quantity' => 'required|numeric|min:0.01',
            'services.*.unit_price' => 'required|numeric|min:0',
            'parts' => 'sometimes|array',
            'parts.*.name' => 'required_with:parts|string|max:255',
            'parts.*.quantity' => 'required_with:parts|numeric|min:0.01',
            'parts.*.unit_price' => 'required_with:parts|numeric|min:0',
            'send_quote' => 'sometimes|boolean',
        ];
    }
}
