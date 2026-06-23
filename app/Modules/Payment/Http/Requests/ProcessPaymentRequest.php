<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Requests;

use App\Modules\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.required' => 'A payment method is required.',
            'method.Illuminate\Validation\Rules\Enum' => 'The selected payment method is not supported.',
        ];
    }
}
