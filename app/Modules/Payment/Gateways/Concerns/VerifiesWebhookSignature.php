<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways\Concerns;

/**
 * Shared HMAC-SHA256 webhook signature verification for gateways. Expects the
 * implementing gateway to expose its config array (including `webhook_secret`).
 */
trait VerifiesWebhookSignature
{
    public function verifySignature(string $payload, string $signature): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');

        if ($secret === '' || $signature === '') {
            return false;
        }

        // hash_equals is constant-time, defeating signature timing attacks.
        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }
}
