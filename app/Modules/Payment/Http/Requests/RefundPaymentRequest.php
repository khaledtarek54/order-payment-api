<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Optional partial amount; omit to refund the full remaining balance.
            // decimal:0,2 rejects sub-cent values so the amount can't be silently
            // rounded to something other than what the caller requested.
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'The refund amount must be greater than zero.',
        ];
    }
}
