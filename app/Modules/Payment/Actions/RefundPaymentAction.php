<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentRefunded;
use App\Modules\Payment\Exceptions\PaymentNotRefundableException;
use App\Modules\Payment\Exceptions\RefundDeclinedException;
use App\Modules\Payment\Exceptions\RefundExceedsPaymentException;
use App\Modules\Payment\Gateways\Data\GatewayRefundData;
use App\Modules\Payment\Gateways\PaymentGatewayManager;
use App\Modules\Payment\Models\Payment;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Refunds money against a successful payment — fully or partially. Refunds are a
 * first-class money movement, not a status flip: the action guards refundability
 * and over-refunds, records the running refunded total + gateway reference, and
 * sets the payment to partially_refunded or refunded accordingly.
 */
final class RefundPaymentAction
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    /**
     * @param  Money|null  $amount  The amount to refund; null refunds the full remaining balance.
     */
    public function execute(Payment $payment, ?Money $amount = null): Payment
    {
        $refunded = DB::transaction(function () use ($payment, $amount): Payment {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isRefundable()) {
                throw new PaymentNotRefundableException;
            }

            $remaining = $locked->amount->minus($locked->refunded_amount);
            $toRefund = $amount ?? $remaining;

            if ($toRefund->isZero() || $toRefund->greaterThan($remaining)) {
                throw new RefundExceedsPaymentException;
            }

            $response = $this->gateways->for($locked->method)->refund(new GatewayRefundData(
                reference: (string) $locked->gateway_reference,
                amount: $toRefund,
            ));

            if (! $response->successful) {
                throw new RefundDeclinedException;
            }

            $newRefundedTotal = $locked->refunded_amount->plus($toRefund);
            $refunds = $locked->gateway_response['refunds'] ?? [];
            $refunds[] = ['reference' => $response->reference, 'amount' => $toRefund->toDecimalString()];

            $locked->update([
                'refunded_amount' => $newRefundedTotal,
                'status' => $newRefundedTotal->equals($locked->amount)
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
                'gateway_response' => array_merge($locked->gateway_response ?? [], ['refunds' => $refunds]),
            ]);

            return $locked;
        });

        $fresh = $refunded->refresh();

        PaymentRefunded::dispatch($fresh, $amount ?? $fresh->refunded_amount);

        return $fresh;
    }
}
