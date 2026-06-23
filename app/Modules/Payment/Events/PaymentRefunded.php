<?php

declare(strict_types=1);

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\Payment;
use App\Support\ValueObjects\Money;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised when money is refunded against a payment (fully or partially), so
 * downstream concerns (fulfilment reversal, notifications, ledger) can react.
 */
class PaymentRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Payment $payment,
        public Money $amount,
    ) {}
}
