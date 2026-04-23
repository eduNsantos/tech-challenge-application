<?php

namespace App\Presentation\Http\Requests;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceOrderRequest extends FormRequest
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
            'id' => 'required|uuid',
            'vehicle_brand' => 'sometimes|string|max:255',
            'vehicle_model' => 'sometimes|string|max:255',
            'vehicle_year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'vehicle_plate' => 'sometimes|string|max:10',
            'services'              => 'sometimes|array|min:1',
            'services.*.service_id' => 'required_with:services|uuid',
            'services.*.quantity'   => 'required_with:services|numeric|min:0.01',
            'parts'                 => 'sometimes|array',
            'parts.*.item_id'       => 'required_with:parts|uuid',
            'parts.*.quantity'      => 'required_with:parts|numeric|min:0.01',
            'status' => ['sometimes', 'string', Rule::in(ServiceOrder::allowedStatuses())],
            'send_quote' => 'sometimes|boolean',
            'approve_quote' => 'sometimes|boolean',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }
}
