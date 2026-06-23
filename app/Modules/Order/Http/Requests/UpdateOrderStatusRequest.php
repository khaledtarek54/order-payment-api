<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Requests;

use App\Modules\Order\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
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
            // Clients may only confirm or cancel. `paid` is reached ONLY through
            // the payment flow (the listener calls the action directly), so it is
            // deliberately excluded here — otherwise an owner could mark their own
            // order paid without paying.
            'status' => ['required', Rule::enum(OrderStatus::class)->only([
                OrderStatus::Confirmed,
                OrderStatus::Cancelled,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'A target status is required.',
        ];
    }
}
