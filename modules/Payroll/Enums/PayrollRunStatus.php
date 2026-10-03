<?php

namespace Modules\Payroll\Enums;

use Modules\Shared\Concerns\HasOptions;

/**
 * draft → calculated → reviewed → approved → processed. Recalculation is allowed until approval.
 */
enum PayrollRunStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Calculated = 'calculated';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Processed = 'processed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Calculated],
            self::Calculated => [self::Calculated, self::Reviewed],
            self::Reviewed => [self::Calculated, self::Approved],
            self::Approved => [self::Processed],
            self::Processed => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::Approved, self::Processed], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Calculated => 'blue',
            self::Reviewed => 'purple',
            self::Approved => 'amber',
            self::Processed => 'green',
        };
    }
}
