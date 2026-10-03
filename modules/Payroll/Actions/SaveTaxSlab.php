<?php

namespace Modules\Payroll\Actions;

use Modules\Payroll\Events\PayrollConfigChanged;
use Modules\Payroll\Models\TaxSlab;
use Modules\Shared\Exceptions\DomainException;

final class SaveTaxSlab
{
    /**
     * @param  array{min_amount: int, max_amount: int|null, rate_bp: int}  $attributes
     */
    public function handle(TaxSlab $slab, array $attributes, ?int $actorId = null): TaxSlab
    {
        $min = $attributes['min_amount'];
        $max = $attributes['max_amount'];

        if ($max !== null && $max <= $min) {
            throw new DomainException(__('Maximum must be greater than minimum.'), 'max_amount');
        }

        $overlaps = TaxSlab::query()
            ->when($slab->exists, fn ($q) => $q->whereKeyNot($slab->id))
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>', $min))
            ->when($max !== null, fn ($q) => $q->where('min_amount', '<', $max))
            ->exists();

        if ($overlaps) {
            throw new DomainException(__('This range overlaps another tax slab.'), 'min_amount');
        }

        $creating = ! $slab->exists;
        $before = $creating ? [] : $slab->only(array_keys($attributes));

        $slab->fill($attributes)->save();

        PayrollConfigChanged::dispatch($slab, $creating ? 'created' : 'updated', "{$min}–".($max ?? '∞'), $before, $attributes, $actorId);

        return $slab;
    }
}
