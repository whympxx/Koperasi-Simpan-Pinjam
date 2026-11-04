<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Pengaturan sistem yang dapat diubah pengurus (tabel system_settings),
 * dengan nilai bawaan dari config/koperasi.php.
 */
class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Setting::all() as $row) {
                self::$cache[$row->setting_key] = self::cast($row->setting_value, $row->setting_type);
            }
        }

        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        if (array_key_exists($key, $all) && $all[$key] !== '' && $all[$key] !== null) {
            return $all[$key];
        }

        return $default ?? config('koperasi.defaults.'.$key);
    }

    public static function float(string $key): float
    {
        return (float) self::get($key);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    public static function set(string $key, $value, string $type = 'number', ?string $description = null, ?int $userId = null): void
    {
        $stored = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        $setting = Setting::firstOrNew(['setting_key' => $key]);
        $setting->setting_value = $stored;
        $setting->setting_type = $type;
        $setting->updated_by = $userId;
        if ($description !== null) {
            $setting->description = $description;
        }
        $setting->save();

        self::flush();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function cast($value, string $type)
    {
        return match ($type) {
            'number' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
