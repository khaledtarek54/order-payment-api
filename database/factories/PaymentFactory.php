<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->confirmed(),
            'status' => PaymentStatus::Pending,
            'method' => PaymentMethod::CreditCard,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'gateway_reference' => null,
            'gateway_response' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Successful,
            'gateway_reference' => 'ref_'.$this->faker->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => PaymentStatus::Failed]);
    }
}
