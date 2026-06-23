<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentProcessed;
use App\Modules\Payment\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Settles a pending payment from an inbound gateway webhook. Idempotent: only a
 * Pending payment transitions, so a redelivered or duplicate webhook for an
 * already-settled payment is a safe no-op. The raw event is stored for audit.
 */
final class SettlePaymentAction
{
    /**
     * @param  array<string, mixed>  $rawEvent
     */
    public function execute(Payment $payment, PaymentStatus $status, array $rawEvent): Payment
    {
        $settled = DB::transaction(function () use ($payment, $status, $rawEvent): bool {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Pending) {
                return false;
            }

            $locked->update([
                'status' => $status,
                'gateway_response' => array_merge($locked->gateway_response ?? [], ['webhook' => $rawEvent]),
            ]);

            return true;
        });

        $payment = $payment->fresh() ?? $payment;

        // Fire after commit so the order-advance listener sees the settled row.
        if ($settled) {
            PaymentProcessed::dispatch($payment);
        }

        return $payment;
    }
}
