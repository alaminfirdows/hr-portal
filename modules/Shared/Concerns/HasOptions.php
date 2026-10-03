<?php

namespace Modules\Shared\Concerns;

use Illuminate\Support\Str;

/**
 * For string-backed enums: human labels and select options.
 */
trait HasOptions
{
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
