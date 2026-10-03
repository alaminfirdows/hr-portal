<?php

namespace Modules\Shared;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;
use Modules\Shared\Contracts\AttendanceSummary;
use Modules\Shared\Contracts\AuditTrail;
use Modules\Shared\Contracts\LeaveCalendar;
use Modules\Shared\Contracts\NotificationPreferences;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Contracts\ProjectMembership;
use Modules\Shared\Contracts\WorkCalendar;
use Modules\Shared\Null\NullAttendanceSummary;
use Modules\Shared\Null\NullAuditTrail;
use Modules\Shared\Null\NullLeaveCalendar;
use Modules\Shared\Null\NullNotificationPreferences;
use Modules\Shared\Null\NullOrgHierarchy;
use Modules\Shared\Null\NullProjectMembership;
use Modules\Shared\Null\WeekdayWorkCalendar;
use Modules\Shared\Support\ModuleRegistry;
use Modules\Shared\Support\ModuleServiceProvider;

class SharedServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Shared';
    }

    public function register(): void
    {
        $this->app->singletonIf(ModuleRegistry::class);

        // Null implementations; owning modules rebind the real ones.
        $this->app->singletonIf(OrgHierarchy::class, NullOrgHierarchy::class);
        $this->app->singletonIf(WorkCalendar::class, WeekdayWorkCalendar::class);
        $this->app->singletonIf(LeaveCalendar::class, NullLeaveCalendar::class);
        $this->app->singletonIf(AttendanceSummary::class, NullAttendanceSummary::class);
        $this->app->singletonIf(ProjectMembership::class, NullProjectMembership::class);
        $this->app->singletonIf(AuditTrail::class, NullAuditTrail::class);
        $this->app->singletonIf(NotificationPreferences::class, NullNotificationPreferences::class);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        Gate::guessPolicyNamesUsing(function (string $class): string {
            if (str_starts_with($class, 'Modules\\')) {
                return str_replace('\\Models\\', '\\Policies\\', $class).'Policy';
            }

            return 'App\\Policies\\'.class_basename($class).'Policy';
        });

        Factory::guessFactoryNamesUsing(function (string $model): string {
            $factory = str_starts_with($model, 'Modules\\')
                ? str_replace('\\Models\\', '\\Database\\Factories\\', $model).'Factory'
                : 'Database\\Factories\\'.class_basename($model).'Factory';

            return is_subclass_of($factory, Factory::class) ? $factory : throw new LogicException("No factory [{$factory}] for [{$model}].");
        });

        Factory::guessModelNamesUsing(function (Factory $factory): string {
            $class = $factory::class;

            $model = str_starts_with($class, 'Modules\\')
                ? Str::replaceLast('Factory', '', str_replace('\\Database\\Factories\\', '\\Models\\', $class))
                : 'App\\Models\\'.Str::replaceLast('Factory', '', class_basename($class));

            return is_subclass_of($model, Model::class) ? $model : throw new LogicException("No model [{$model}] for [{$class}].");
        });
    }
}
