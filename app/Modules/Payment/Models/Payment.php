<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Support\Casts\MoneyCast;
use App\Support\ValueObjects\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $order_id
 * @property PaymentStatus $status
 * @property PaymentMethod $method
 * @property Money $amount
 * @property string|null $gateway_reference
 * @property array<string, mixed>|null $gateway_response
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'status',
        'method',
        'amount',
        'gateway_reference',
        'gateway_response',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'amount' => MoneyCast::class,
            'gateway_response' => 'array',
        ];
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
