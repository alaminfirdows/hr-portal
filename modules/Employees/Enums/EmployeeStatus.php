<?php

namespace Modules\Employees\Enums;

use Modules\Shared\Concerns\HasOptions;

/**
 * invited → active ⇄ on_leave, active ⇄ suspended, any → terminated (terminal).
 */
enum EmployeeStatus: string
{
    use HasOptions;

    case Invited = 'invited';
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Invited => [self::Active, self::Terminated],
            self::Active => [self::OnLeave, self::Suspended, self::Terminated],
            self::OnLeave => [self::Active, self::Terminated],
            self::Suspended => [self::Active, self::Terminated],
            self::Terminated => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isEmployed(): bool
    {
        return in_array($this, [self::Active, self::OnLeave, self::Suspended], true);
    }

    /**
     * @return list<string>
     */
    public static function employedValues(): array
    {
        return [self::Active->value, self::OnLeave->value, self::Suspended->value];
    }

    public function color(): string
    {
        return match ($this) {
            self::Invited => 'blue',
            self::Active => 'green',
            self::OnLeave => 'amber',
            self::Suspended => 'orange',
            self::Terminated => 'red',
        };
    }
}
