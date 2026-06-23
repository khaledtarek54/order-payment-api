<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Models\Payment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with a known demo account plus a handful
     * of random users, each with orders across every status and payments on
     * their confirmed orders.
     *
     * Payments are seeded directly in their final state (successful/failed) so
     * the data is complete without a running queue worker.
     */
    public function run(): void
    {
        // Stable account for manual testing: demo@example.com / password
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        $this->seedDemoOrders($demo);

        // A few extra users with assorted orders.
        User::factory(4)->create()->each(function (User $user): void {
            foreach (range(1, random_int(1, 3)) as $ignored) {
                $status = collect(OrderStatus::cases())->random();
                $order = $this->makeOrder($user, $status);

                if ($status === OrderStatus::Confirmed) {
                    $this->payForOrder($order);
                }
            }
        });
    }

    /**
     * A deterministic spread for the demo user covering every state the API
     * exposes: pending, confirmed + paid, confirmed + failed payment, cancelled.
     */
    private function seedDemoOrders(User $user): void
    {
        $this->makeOrder($user, OrderStatus::Pending, 'Awaiting confirmation');

        $confirmed = $this->makeOrder($user, OrderStatus::Confirmed, 'Paid in full');
        $this->payForOrder($confirmed, PaymentMethod::CreditCard, successful: true);

        $declined = $this->makeOrder($user, OrderStatus::Confirmed, 'Card declined');
        $this->payForOrder($declined, PaymentMethod::Paypal, successful: false);

        $this->makeOrder($user, OrderStatus::Cancelled, 'Customer cancelled');
    }

    /**
     * Create an order with 1–4 items and a server-computed total.
     */
    private function makeOrder(User $user, OrderStatus $status, ?string $notes = null): Order
    {
        $order = Order::factory()->for($user)->create([
            'status' => $status,
            'notes' => $notes,
            'total' => 0,
        ]);

        $items = OrderItem::factory()->count(random_int(1, 4))->for($order)->create();
        $order->update(['total' => $items->sum('line_total')]);

        return $order;
    }

    private function payForOrder(
        Order $order,
        PaymentMethod $method = PaymentMethod::CreditCard,
        bool $successful = true,
    ): void {
        $factory = Payment::factory()->for($order)->state([
            'method' => $method,
            'amount' => $order->total,
        ]);

        ($successful ? $factory->successful() : $factory->failed())->create();
    }
}
