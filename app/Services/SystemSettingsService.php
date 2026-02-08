<?php

namespace App\Services;

use App\Models\SystemSetting;

class SystemSettingsService
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return SystemSetting::getValue($group, $key, $default);
    }

    public function set(string $group, string $key, mixed $value): void
    {
        SystemSetting::setValue($group, $key, $value);
    }

    public function group(string $group): array
    {
        return SystemSetting::getGroup($group);
    }
}
