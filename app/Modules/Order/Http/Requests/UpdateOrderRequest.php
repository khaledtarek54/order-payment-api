<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
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
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.min' => 'At least one order item is required when updating items.',
            'items.*.product_name.required_with' => 'Each item requires a product name.',
            'items.*.quantity.required_with' => 'Each item requires a quantity.',
            'items.*.quantity.min' => 'Each item quantity must be at least 1.',
            'items.*.unit_price.required_with' => 'Each item requires a unit price.',
            'items.*.unit_price.min' => 'Each item unit price must be 0 or greater.',
        ];
    }
}
