<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Settings
{
    public static function all()
    {
        return Cache::remember('sys_settings', 3600, function () {
            $settings = DB::table('sys_setting')->first();

            if (!$settings) return [];

            unset($settings->ID, $settings->created_at);

            return (array) $settings;
        });
    }

    public static function get($key, $default = null)
    {
        $settings = self::all();
        return $settings[$key] ?? $default;
    }

    public static function clearCache()
    {
        Cache::forget('sys_settings');
    }
}
