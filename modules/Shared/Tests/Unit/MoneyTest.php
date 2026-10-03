<?php

use Modules\Shared\Enums\Scope;
use Modules\Shared\ValueObjects\Money;

it('builds money from decimals with half-up rounding', function () {
    expect(Money::fromDecimal('10.005')->minor)->toBe(1001)
        ->and(Money::fromDecimal(12)->minor)->toBe(1200);
});

it('adds, subtracts and multiplies', function () {
    $money = Money::of(1000)->add(Money::of(250))->subtract(Money::of(50))->multiply(0.5);

    expect($money->minor)->toBe(600)
        ->and($money->format('USD'))->toBe('USD 6.00');
});

it('rejects non numeric amounts', function () {
    Money::fromDecimal('abc');
})->throws(InvalidArgumentException::class);

it('compares scopes', function () {
    expect(Scope::All->covers(Scope::Team))->toBeTrue()
        ->and(Scope::Own->covers(Scope::Team))->toBeFalse()
        ->and(Scope::widest(Scope::Own, Scope::Department, Scope::Team))->toBe(Scope::Department)
        ->and(Scope::widest())->toBeNull();
});
