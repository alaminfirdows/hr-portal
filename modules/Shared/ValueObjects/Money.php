<?php

namespace Modules\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Money in integer minor units (e.g. cents). Single currency per install.
 */
final readonly class Money implements JsonSerializable
{
    private function __construct(public int $minor) {}

    public static function of(int $minor): self
    {
        return new self($minor);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromDecimal(string|int|float $amount): self
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        return new self((int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP));
    }

    public function add(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function subtract(self $other): self
    {
        return new self($this->minor - $other->minor);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->minor * $factor, 0, PHP_ROUND_HALF_UP));
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function max(self $other): self
    {
        return $this->minor >= $other->minor ? $this : $other;
    }

    public function toDecimal(): float
    {
        return $this->minor / 100;
    }

    public function format(string $currency = ''): string
    {
        $formatted = number_format($this->minor / 100, 2);

        return $currency === '' ? $formatted : "{$currency} {$formatted}";
    }

    public function jsonSerialize(): int
    {
        return $this->minor;
    }
}
