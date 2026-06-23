<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways\Data;

use App\Modules\Payment\Enums\PaymentStatus;
use Spatie\LaravelData\Data;

/**
 * Normalised result returned by every gateway, regardless of the underlying
 * provider's own response shape.
 */
class GatewayResponse extends Data
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public PaymentStatus $status,
        public ?string $reference = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function successful(string $reference, array $raw = []): self
    {
        return new self(
            successful: true,
            status: PaymentStatus::Successful,
            reference: $reference,
            raw: $raw,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failed(string $message, array $raw = []): self
    {
        return new self(
            successful: false,
            status: PaymentStatus::Failed,
            message: $message,
            raw: $raw,
        );
    }
}
