<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Settings\Data\OrganizationSettings;
use Modules\Settings\Models\Setting;

/**
 * Key/value organization settings, cached forever and busted on write.
 */
final class SettingsRepository
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, mixed>|null */
    private ?array $memo = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? OrganizationSettings::DEFAULTS[$key] ?? null);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        if (! Schema::hasTable('settings')) {
            return $this->memo = [];
        }

        /** @var array<string, mixed> $values */
        $values = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return $this->memo = $values;
    }

    public function organization(): OrganizationSettings
    {
        return OrganizationSettings::fromArray([...OrganizationSettings::DEFAULTS, ...$this->all()]);
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }
}
