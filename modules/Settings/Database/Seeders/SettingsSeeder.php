<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Settings\Data\OrganizationSettings;
use Modules\Settings\Models\Setting;
use Modules\Settings\Services\SettingsRepository;

class SettingsSeeder extends Seeder
{
    public function run(SettingsRepository $settings): void
    {
        foreach (OrganizationSettings::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $settings->flush();
    }
}
