<?php 


namespace App\Support;

use Illuminate\Support\Facades\DB;

class Settings
{
    public static function all()
    {
        return cache()->remember('sys_settings', 3600, function () {
            return DB::table('sys_setting')->pluck('value', 'key')->toArray();
        });
    }

    public static function get($key, $default = null)
    {
        return self::all()[$key] ?? $default;
    }
}
