<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'total' => 0,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => ['status' => OrderStatus::Confirmed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => OrderStatus::Cancelled]);
    }
}
