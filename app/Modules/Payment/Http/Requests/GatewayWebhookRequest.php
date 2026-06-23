<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GatewayWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authenticity is established by VerifyGatewaySignature, not a user token.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string'],
            'status' => ['required', Rule::in(['successful', 'failed'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.required' => 'The gateway payment reference is required.',
            'status.in' => 'The webhook status must be either successful or failed.',
        ];
    }
}
