<?php

namespace Modules\Organization\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Organization\Database\Factories\HolidayFactory;

/**
 * @property int $id
 * @property string $name
 * @property CarbonImmutable $date
 * @property bool $is_optional
 */
#[Fillable(['name', 'date', 'is_optional'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'is_optional' => 'boolean',
        ];
    }
}
