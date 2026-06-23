<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Resources;

use App\Modules\Payment\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status->value,
            'method' => $this->method->value,
            'amount' => $this->amount->toDecimalString(),
            'gateway_reference' => $this->gateway_reference,
            'created_at' => $this->created_at,
        ];
    }
}
