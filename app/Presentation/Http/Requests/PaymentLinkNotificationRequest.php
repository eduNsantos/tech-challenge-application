<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload enviado pelo payment-service (Step ③ notify-wait) para APP_NOTIFICATION_URL.
 */
class PaymentLinkNotificationRequest extends FormRequest
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
            'payment_link' => ['required', 'url:https'],
            'status' => ['sometimes', 'string'],
        ];
    }
}
