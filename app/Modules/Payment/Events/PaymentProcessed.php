<?php

declare(strict_types=1);

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
