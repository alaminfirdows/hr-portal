<?php

namespace Modules\Announcements\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Announcements\Database\Factories\AnnouncementFactory;
use Modules\Announcements\Enums\Audience;
use Modules\Announcements\Enums\Priority;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\OrgHierarchy;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property Priority $priority
 * @property Audience $audience
 * @property CarbonImmutable $publish_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $dispatched_at
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read User|null $author
 * @property-read bool|null $is_read
 * @property-read int|null $reads_count
 */
#[Fillable(['title', 'body', 'priority', 'audience', 'publish_at', 'expires_at', 'dispatched_at', 'created_by'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'audience' => Audience::class,
            'publish_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnnouncementTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    /**
     * @return HasMany<AnnouncementRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * @return list<int>
     */
    public function targetIds(): array
    {
        /** @var list<int> $ids */
        $ids = $this->targets()->pluck('target_id')->map(fn ($id) => (int) $id)->all();

        return $ids;
    }

    public function isLive(): bool
    {
        return $this->publish_at->lte(now()) && ($this->expires_at === null || $this->expires_at->gt(now()));
    }

    /**
     * Published and not yet expired. Scheduled and expired announcements are excluded.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('publish_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Announcements whose audience includes the user. Does not check publication state.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $org = app(OrgHierarchy::class);
        $employeeId = $org->employeeIdForUser($user->id);
        $departmentId = $employeeId === null ? null : $org->departmentId($employeeId);
        $teamId = $employeeId === null ? null : $org->teamId($employeeId);

        $targeted = fn (Audience $audience, ?int $id) => $id === null ? null : fn (Builder $q) => $q
            ->where('audience', $audience->value)
            ->whereIn('id', AnnouncementTarget::query()->where('target_id', $id)->select('announcement_id'));

        $query->where(function (Builder $q) use ($targeted, $departmentId, $teamId, $employeeId) {
            $q->where('audience', Audience::All->value);

            foreach ([[Audience::Department, $departmentId], [Audience::Team, $teamId], [Audience::Employees, $employeeId]] as [$audience, $id]) {
                $closure = $targeted($audience, $id);

                if ($closure !== null) {
                    $q->orWhere($closure);
                }
            }
        });
    }

    /**
     * Adds an `is_read` flag for the user.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithReadState(Builder $query, User $user): void
    {
        $query->addSelect([
            'is_read' => AnnouncementRead::query()
                ->selectRaw('1')
                ->whereColumn('announcement_id', 'announcements.id')
                ->where('user_id', $user->id)
                ->limit(1),
        ])->withCasts(['is_read' => 'boolean']);
    }
}
