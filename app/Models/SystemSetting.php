<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    public static function getValue(string $group, string $key, mixed $default = null): mixed
    {
        $setting = static::where('group', $group)->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return json_decode((string) $setting->value, true);
    }

    public static function setValue(string $group, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => json_encode($value)]
        );
    }

    public static function getGroup(string $group): array
    {
        return static::where('group', $group)
            ->pluck('value', 'key')
            ->map(fn ($value): mixed => json_decode((string) $value, true))
            ->toArray();
    }
}
