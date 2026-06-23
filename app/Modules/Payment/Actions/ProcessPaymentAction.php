<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Exceptions\OrderNotConfirmedException;
use App\Modules\Payment\Jobs\ProcessPaymentJob;
use App\Modules\Payment\Models\Payment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Initiates payment for a confirmed order: creates a pending Payment and hands
 * the gateway charge to the queue. Business rule: only confirmed orders may be
 * paid (raises OrderNotConfirmedException → 409).
 *
 * Idempotent when an Idempotency-Key is supplied: a retried or double-submitted
 * request with the same key returns the original Payment instead of charging
 * again. Concurrency is handled three ways — the order row is locked, the key is
 * checked under that lock, and a unique (order_id, idempotency_key) constraint is
 * the final backstop against a lost race.
 */
final class ProcessPaymentAction
{
    public function execute(Order $order, PaymentMethod $method, ?string $idempotencyKey = null): Payment
    {
        /** @var array{0: Payment, 1: bool} $result */
        $result = DB::transaction(function () use ($order, $method, $idempotencyKey): array {
            // Serialise concurrent attempts on this order (no-op on SQLite).
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            // Idempotency replay is checked BEFORE the business rule: a repeated
            // key returns the original payment even if the order has since moved
            // on (e.g. to paid), so a safe client retry never sees a 409.
            if ($idempotencyKey !== null) {
                $existing = $locked->payments()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return [$existing, false];
                }
            }

            // Business rule applies only to a genuinely new payment.
            if (! $locked->isConfirmed()) {
                throw new OrderNotConfirmedException;
            }

            try {
                $payment = $locked->payments()->create([
                    'status' => PaymentStatus::Pending,
                    'method' => $method,
                    'amount' => $locked->total,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request with the same key won the race.
                return [$locked->payments()->where('idempotency_key', $idempotencyKey)->firstOrFail(), false];
            }

            return [$payment, true];
        });

        [$payment, $isNew] = $result;

        // Dispatch after commit so the worker never races the transaction.
        if ($isNew) {
            ProcessPaymentJob::dispatch($payment);
        }

        return $payment->refresh();
    }
}
