<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one order item is required.',
            'items.min' => 'At least one order item is required.',
            'items.*.product_name.required' => 'Each item requires a product name.',
            'items.*.quantity.required' => 'Each item requires a quantity.',
            'items.*.quantity.min' => 'Each item quantity must be at least 1.',
            'items.*.unit_price.required' => 'Each item requires a unit price.',
            'items.*.unit_price.min' => 'Each item unit price must be 0 or greater.',
        ];
    }
}
