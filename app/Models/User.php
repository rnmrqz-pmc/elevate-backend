<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use App\Models\SysLoginAttempt;


class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;
    
    protected $table = 'users';
    protected $primaryKey = 'ID';
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'role_id',
        'username',
        'email',
        'password',
        'profile_complete',
        'agree_terms',
        'last_login',
        'with_2fa',
        'invalid_attempts',
        'is_locked',
        'locked_at',
        'unlocked_at',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    public $incrementing = true;   // default
    protected $keyType = 'int';    // default


// ==================== LOGIN & SESSION ====================
    public function updateLastLogin(): bool {
        $this->last_login = now();
        $this->resetInvalidAttempts();
        return $this->save();
    }
    public function canLogin(): bool {
        return !$this->isLocked() && $this->hasAgreedToTerms();
    }

    // ==================== PROFILE MANAGEMENT ====================
    public function markProfileComplete(?string $updatedBy = null): bool {
        $this->profile_complete = 1;
        $this->updated_by = $updatedBy;
        return $this->save();
    }
    public function acceptTerms(?string $updatedBy = null): bool {
        $this->agree_terms = 1;
        $this->updated_by = $updatedBy;
        return $this->save();
    }
    public function enable2FA(?string $updatedBy = null): bool {
        $this->with_2fa = 1;
        $this->updated_by = $updatedBy;
        return $this->save();
    }
    public function disable2FA(?string $updatedBy = null): bool {
        $this->with_2fa = 0;
        $this->updated_by = $updatedBy;
        return $this->save();
    }

    // ==================== SCOPES ====================
    public function scopeActive($query) {
        return $query->where('is_locked', 0);
    }
    public function scopeLocked($query) {
        return $query->where('is_locked', 1);
    }
    public function scopeProfileComplete($query) {
        return $query->where('profile_complete', 1);
    }
    public function scopeByRole($query, $roleId) {
        return $query->where('role_id', $roleId);
    }
    public function scopeWith2FA($query) {
        return $query->where('with_2fa', 1);
    }

    // ==================== STATIC HELPERS ====================
    public static function createUser(array $data): self {
        $user = new self();
        $user->fill($data);
        
        if (isset($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        
        $user->save();
        return $user;
    }
    public static function findByEmail(string $email): ?self {
        return self::where('email', $email)->first();
    }
    public static function findByUsername(string $username): ?self {
        return self::where('username', $username)->first();
    }



    // ==================== PASSWORD MANAGEMENT ====================
    public function changePassword(string $newPassword, ?string $updatedBy = null): bool {
        $this->password = Hash::make($newPassword);
        $this->updated_by = $updatedBy;
        return $this->save();
    }
    public function verifyPassword(string $password): bool {
        return Hash::check($password, $this->password);
    }
    public function updatePassword(string $oldPassword, string $newPassword, ?string $updatedBy = null): bool {
        if (!$this->verifyPassword($oldPassword)) {
            return false;
        }
        return $this->changePassword($newPassword, $updatedBy);
    }


    // ==================== GETTERS ====================
    public function getDisplayName(): string {
        return $this->username;
    }
    public function isProfileComplete(): bool {
        return (bool) $this->profile_complete;
    }
    public function hasAgreedToTerms(): bool {
        return (bool) $this->agree_terms;
    }
    public function has2FAEnabled(): bool {
        return (bool) $this->with_2fa;
    }
    public function isLocked(): bool {
        return (bool) $this->is_locked;
    }
    public function getRoleName(): ?string {
        return $this->role?->name;
    }
    public function getDaysSinceLastLogin(): ?int {
        return $this->last_login ? 
            Carbon::parse($this->last_login)->diffInDays(now()) : 
            null;
    }

    // ==================== ACCOUNT SECURITY ====================
    public function incrementInvalidAttempts(): void {
        $this->invalid_attempts++;
        $max_login_attempts = Settings::get('max_login_attempts');

        // Lock account after 5 failed attempts
        if ($this->invalid_attempts >= $max_login_attempts) {
            $this->lockAccount();
        }
        $this->save();
        
    }
    public function resetInvalidAttempts(): void {
        $this->invalid_attempts = 0;
        $this->save();
    }
    public function lockAccount(): bool {
        $this->is_locked = 1;
        $this->locked_at = now();
        $this->unlocked_at = null;
        return $this->save();
    }
    public function unlockAccount(?string $updatedBy = null): bool {
        $this->is_locked = 0;
        $this->unlocked_at = now();
        $this->invalid_attempts = 0;
        $this->updated_by = $updatedBy;
        return $this->save();
    }



// JWT Subject methods
    public function getJWTIdentifier() {
        return $this->getKey();
    }
    public function getJWTCustomClaims() {
        return [];
    }
}