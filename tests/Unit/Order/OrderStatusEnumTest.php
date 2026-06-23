<?php

declare(strict_types=1);

use App\Modules\Order\Enums\OrderStatus;

it('allows a pending order to transition to confirmed', function (): void {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed))->toBeTrue();
});

it('allows a pending order to transition to cancelled', function (): void {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled))->toBeTrue();
});

it('allows a confirmed order to transition to cancelled', function (): void {
    expect(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Cancelled))->toBeTrue();
});

it('allows a confirmed order to transition to paid', function (): void {
    expect(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Paid))->toBeTrue();
});

it('forbids skipping confirmation to pay directly from pending', function (): void {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Paid))->toBeFalse();
});

it('treats paid as a terminal status', function (): void {
    expect(OrderStatus::Paid->canTransitionTo(OrderStatus::Confirmed))->toBeFalse();
    expect(OrderStatus::Paid->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
});

it('forbids a confirmed order from reverting to pending', function (): void {
    expect(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Pending))->toBeFalse();
});

it('forbids a cancelled order from transitioning anywhere', function (): void {
    expect(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Pending))->toBeFalse();
    expect(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Confirmed))->toBeFalse();
    expect(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
});
