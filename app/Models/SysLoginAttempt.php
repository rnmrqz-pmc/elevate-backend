<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;



class SysLoginAttempt extends Authenticatable
{
    protected $table = 'sys_login_attempt';
    protected $primaryKey = 'ID';

    // const CREATED_AT = 'created_at';

    protected $fillable = [
        'user_id',
        'username',
        'ip_address',
        'user_agent',
        'success',
        'fail_reason',
    ];
    public $incrementing = true;   // default
    protected $keyType = 'int';    // default


    public static function insert(array $data): self
    {
        $sysLoginAttempt = new self();
        $sysLoginAttempt->fill($data);
        $sysLoginAttempt->save();
        return $sysLoginAttempt;
    }

    public static function getByUserId(int $userId): ?self
    {
        return self::where('user_id', $userId)->first();
    }

    public function getInvalidAttempts(): int
    {
        return $this->where('success', false)->count();
    }

    public function getSuccessAttempts(): int
    {
        return $this->where('success', true)->count();
    }



}