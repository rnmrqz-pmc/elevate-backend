<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Support\Settings;


class User extends Authenticatable
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
        'password_hash',
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
        'password_hash',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'profile_complete' => 'boolean',
        'agree_terms' => 'boolean',
        'with_2fa' => 'boolean',
        'is_locked' => 'boolean',
        'last_login' => 'datetime',
        'locked_at' => 'datetime',
        'unlocked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];




    

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the role that owns the user.
     */
    public function role()
    {
        return $this->belongsTo(UserRole::class, 'role_id', 'ID');
    }

    // ==================== GETTERS ====================

    /**
     * Get user's full display name
     */
    public function getDisplayName(): string
    {
        return $this->username;
    }

    /**
     * Check if user profile is complete
     */
    public function isProfileComplete(): bool
    {
        return (bool) $this->profile_complete;
    }

    /**
     * Check if user has agreed to terms
     */
    public function hasAgreedToTerms(): bool
    {
        return (bool) $this->agree_terms;
    }

    /**
     * Check if 2FA is enabled
     */
    public function has2FAEnabled(): bool
    {
        return (bool) $this->with_2fa;
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }


    /**
     * Get role name
     */
    public function getRoleName(): ?string
    {
        return $this->role?->name;
    }

    /**
     * Get days since last login
     */
    public function getDaysSinceLastLogin(): ?int
    {
        return $this->last_login ? 
            Carbon::parse($this->last_login)->diffInDays(now()) : 
            null;
    }

    // ==================== PASSWORD MANAGEMENT ====================

    /**
     * Change user password
     */
    public function changePassword(string $newPassword, ?string $updatedBy = null): bool
    {
        $this->password_hash = Hash::make($newPassword);
        $this->updated_by = $updatedBy;
        return $this->save();
    }

    /**
     * Verify if the given password matches
     */
    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->password_hash);
    }

    /**
     * Update password with old password verification
     */
    public function updatePassword(string $oldPassword, string $newPassword, ?string $updatedBy = null): bool
    {
        if (!$this->verifyPassword($oldPassword)) {
            return false;
        }
        
        return $this->changePassword($newPassword, $updatedBy);
    }

    // ==================== ACCOUNT SECURITY ====================

    /**
     * Increment invalid login attempts
     */
    public function incrementInvalidAttempts(): void
    {
        $this->invalid_attempts++;

        $max_login_attempts = Settings::get('max_login_attempts');

        // Lock account after 5 failed attempts
        if ($this->invalid_attempts >= $max_login_attempts) {
            $this->lockAccount();
        }
        
        $this->save();
    }

    /**
     * Reset invalid login attempts
     */
    public function resetInvalidAttempts(): void
    {
        $this->invalid_attempts = 0;
        $this->save();
    }

    /**
     * Lock user account
     */
    public function lockAccount(): bool
    {
        $this->is_locked = 1;
        $this->locked_at = now();
        $this->unlocked_at = null;
        return $this->save();
    }

    /**
     * Unlock user account
     */
    public function unlockAccount(?string $updatedBy = null): bool
    {
        $this->is_locked = 0;
        $this->unlocked_at = now();
        $this->invalid_attempts = 0;
        $this->updated_by = $updatedBy;
        return $this->save();
    }

    // ==================== LOGIN & SESSION ====================

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): bool
    {
        $this->last_login = now();
        $this->resetInvalidAttempts();
        return $this->save();
    }

    /**
     * Check if user can login
     */
    public function canLogin(): bool
    {
        return !$this->isLocked() && $this->hasAgreedToTerms();
    }

    // ==================== PROFILE MANAGEMENT ====================

    /**
     * Mark profile as complete
     */
    public function markProfileComplete(?string $updatedBy = null): bool
    {
        $this->profile_complete = 1;
        $this->updated_by = $updatedBy;
        return $this->save();
    }

    /**
     * Accept terms and conditions
     */
    public function acceptTerms(?string $updatedBy = null): bool
    {
        $this->agree_terms = 1;
        $this->updated_by = $updatedBy;
        return $this->save();
    }

    /**
     * Enable 2FA
     */
    public function enable2FA(?string $updatedBy = null): bool
    {
        $this->with_2fa = 1;
        $this->updated_by = $updatedBy;
        return $this->save();
    }

    /**
     * Disable 2FA
     */
    public function disable2FA(?string $updatedBy = null): bool
    {
        $this->with_2fa = 0;
        $this->updated_by = $updatedBy;
        return $this->save();
    }

    // ==================== SCOPES ====================

    /**
     * Scope to get only active (unlocked) users
     */
    public function scopeActive($query)
    {
        return $query->where('is_locked', 0);
    }

    /**
     * Scope to get locked users
     */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', 1);
    }

    /**
     * Scope to get users with complete profiles
     */
    public function scopeProfileComplete($query)
    {
        return $query->where('profile_complete', 1);
    }

    /**
     * Scope to get users by role
     */
    public function scopeByRole($query, $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    /**
     * Scope to get users with 2FA enabled
     */
    public function scopeWith2FA($query)
    {
        return $query->where('with_2fa', 1);
    }

    // ==================== STATIC HELPERS ====================

    /**
     * Create a new user
     */
    public static function createUser(array $data): self
    {
        $user = new self();
        $user->fill($data);
        
        if (isset($data['password'])) {
            $user->password_hash = Hash::make($data['password']);
        }
        
        $user->save();
        return $user;
    }

    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?self
    {
        return self::where('email', $email)->first();
    }

    /**
     * Find user by username
     */
    public static function findByUsername(string $username): ?self
    {
        return self::where('username', $username)->first();
    }
}