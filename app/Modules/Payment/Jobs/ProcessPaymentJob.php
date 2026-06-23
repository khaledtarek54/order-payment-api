<?php

declare(strict_types=1);

namespace App\Modules\Payment\Jobs;

use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentProcessed;
use App\Modules\Payment\Gateways\Data\GatewayChargeData;
use App\Modules\Payment\Gateways\PaymentGatewayManager;
use App\Modules\Payment\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs the gateway charge off the request cycle.
 *
 * Reliability under at-least-once delivery: the job is unique per payment
 * (ShouldBeUnique) and retried with backoff, and handle() re-reads the payment
 * under a row lock and only charges while it is still Pending — so a retry,
 * redelivery, or duplicate dispatch can never charge the same payment twice.
 */
class ProcessPaymentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Payment $payment) {}

    /** One in-flight job per payment. */
    public function uniqueId(): string
    {
        return (string) $this->payment->getKey();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(PaymentGatewayManager $manager): void
    {
        $charged = DB::transaction(function () use ($manager): ?Payment {
            $payment = Payment::query()->whereKey($this->payment->getKey())->lockForUpdate()->first();

            // Already processed (retry / redelivery / duplicate) — do not re-charge.
            if ($payment === null || $payment->status !== PaymentStatus::Pending) {
                return null;
            }

            $response = $manager->for($payment->method)->charge(new GatewayChargeData(
                paymentId: (string) $payment->getKey(),
                orderId: (int) $payment->order_id,
                amount: $payment->amount,
            ));

            $payment->update([
                'status' => $response->status,
                'gateway_reference' => $response->reference,
                'gateway_response' => $response->raw + ['message' => $response->message],
            ]);

            return $payment;
        });

        if ($charged !== null) {
            PaymentProcessed::dispatch($charged->refresh());
        }
    }

    /**
     * Exhausted retries (or a thrown gateway error): record the failure so the
     * payment never sticks on Pending.
     */
    public function failed(?Throwable $e): void
    {
        $payment = $this->payment->fresh();

        if ($payment !== null && $payment->status === PaymentStatus::Pending) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'gateway_response' => ['error' => $e?->getMessage() ?? 'Payment processing failed.'],
            ]);

            PaymentProcessed::dispatch($payment->refresh());
        }
    }
}
