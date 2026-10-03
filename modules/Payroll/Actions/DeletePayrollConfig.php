<?php

namespace Modules\Payroll\Actions;

use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Events\PayrollConfigChanged;

/**
 * Deletes a pay component, tax slab or adjustment and records it.
 */
final class DeletePayrollConfig
{
    public function handle(Model $model, string $label, ?int $actorId = null): void
    {
        $before = $model->getAttributes();
        $model->delete();

        PayrollConfigChanged::dispatch($model, 'deleted', $label, $before, [], $actorId);
    }
}
