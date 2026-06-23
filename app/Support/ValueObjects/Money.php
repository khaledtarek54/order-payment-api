<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An immutable money value stored as integer minor units (e.g. cents) to avoid
 * IEEE-754 rounding — the classic "0.1 + 0.2" bug that silently corrupts
 * financial totals. All arithmetic is integer arithmetic; decimals only appear
 * at the edges (parsing input, formatting output).
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public function __construct(
        public int $minorUnits,
        public string $currency = 'USD',
    ) {}

    public static function fromMinorUnits(int $minorUnits, string $currency = 'USD'): self
    {
        return new self($minorUnits, $currency);
    }

    /**
     * Build from a major-unit amount (e.g. 24.99). Parsed via string arithmetic
     * — never `(float) * 100` — so binary representation error can't make e.g.
     * 1.005 round down to 1.00. Sub-cent precision is rounded half-up to the
     * nearest cent; all later math then stays in integers.
     */
    public static function fromDecimal(int|float|string $amount, string $currency = 'USD'): self
    {
        // Render floats with fixed precision (no scientific notation) so we can
        // parse the intended decimal rather than its raw IEEE-754 expansion.
        $string = is_float($amount) ? sprintf('%.4F', $amount) : trim((string) $amount);

        $negative = str_starts_with($string, '-');
        $string = ltrim($string, '+-');

        [$whole, $fraction] = array_pad(explode('.', $string, 2), 2, '');

        // Keep three fractional digits (tenths of a cent) for half-up rounding.
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        $tenthsOfCents = ((int) $whole) * 1000 + (int) $fraction;
        $minorUnits = intdiv($tenthsOfCents + 5, 10); // round half-up to cents

        return new self($negative ? -$minorUnits : $minorUnits, $currency);
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function times(int $quantity): self
    {
        return new self($this->minorUnits * $quantity, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits > $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
    }

    /** Fixed-point decimal string ("24.99") with no float involved. */
    public function toDecimalString(): string
    {
        $sign = $this->minorUnits < 0 ? '-' : '';
        $abs = abs($this->minorUnits);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    public function toFloat(): float
    {
        return $this->minorUnits / 100;
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimalString();
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}.",
            );
        }
    }
}
