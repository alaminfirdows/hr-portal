<?php

namespace Modules\Employees\Notifications;

use Illuminate\Support\Str;
use Modules\Employees\Models\Employee;
use Modules\Shared\Notifications\PortalNotification;

final class ProfileChangedNotification extends PortalNotification
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(public readonly Employee $employee, public readonly array $fields) {}

    public function type(): string
    {
        return 'employees.profile_changed';
    }

    public function title(): string
    {
        return __('Your employee profile was updated');
    }

    public function body(): string
    {
        $fields = collect($this->fields)->map(fn (string $f) => Str::of($f)->replaceLast('_id', '')->headline()->lower())->implode(', ');

        return __('HR updated: :fields.', ['fields' => $fields]);
    }

    public function url(): string
    {
        return route('employees.profile.edit');
    }

    public function icon(): string
    {
        return 'user-pen';
    }

    public function defaultChannels(): array
    {
        return ['in_app'];
    }
}
