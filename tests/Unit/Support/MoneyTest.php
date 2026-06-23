<?php

declare(strict_types=1);

use App\Support\ValueObjects\Money;

it('parses a decimal amount into minor units', function (): void {
    expect(Money::fromDecimal('24.99')->minorUnits)->toBe(2499)
        ->and(Money::fromDecimal(10)->minorUnits)->toBe(1000)
        ->and(Money::fromDecimal(2.5)->minorUnits)->toBe(250);
});

it('formats minor units as a fixed-point decimal string', function (): void {
    expect(Money::fromMinorUnits(4000)->toDecimalString())->toBe('40.00')
        ->and(Money::fromMinorUnits(5)->toDecimalString())->toBe('0.05')
        ->and(Money::fromMinorUnits(100000)->toDecimalString())->toBe('1000.00');
});

it('adds without floating-point error (the 0.1 + 0.2 trap)', function (): void {
    $sum = Money::fromDecimal(0.1)->plus(Money::fromDecimal(0.2));

    expect($sum->minorUnits)->toBe(30)
        ->and($sum->toDecimalString())->toBe('0.30');
});

it('sums many line items exactly', function (): void {
    $total = Money::zero();

    // 10 items of 0.10 each => exactly 1.00, where float accumulation drifts.
    foreach (range(1, 10) as $ignored) {
        $total = $total->plus(Money::fromDecimal(0.10));
    }

    expect($total->toDecimalString())->toBe('1.00');
});

it('multiplies by a quantity', function (): void {
    expect(Money::fromDecimal(2.5)->times(4)->toDecimalString())->toBe('10.00');
});

it('refuses to mix currencies', function (): void {
    Money::fromMinorUnits(100, 'USD')->plus(Money::fromMinorUnits(100, 'EUR'));
})->throws(InvalidArgumentException::class);
