<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a decimal money column to/from the {@see Money} value object. Reads
 * become Money (integer minor units); writes accept a Money or any numeric and
 * are stored as a fixed-point decimal string the column understands.
 *
 * @implements CastsAttributes<Money, Money|int|float|string>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::fromDecimal((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $money = $value instanceof Money ? $value : Money::fromDecimal($value);

        return $money->toDecimalString();
    }
}
