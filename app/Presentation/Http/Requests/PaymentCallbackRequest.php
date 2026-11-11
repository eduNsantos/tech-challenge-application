<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Resultado final da cobrança enviado pelo payment-service ao callback_url (PAID, REJECTED, EXPIRED...).
 */
class PaymentCallbackRequest extends FormRequest
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
            'order_id' => ['required', 'uuid'],
            'status' => ['required', 'string'],
            'mp_payment_id' => ['nullable'],
        ];
    }
}
