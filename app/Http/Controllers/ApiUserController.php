<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\Settings;


class ApiUserController extends Controller
{
    // ==================== AUTHENTICATION ====================

    /**
     * User login
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $payload = $request->all();
        $login_type = isset($payload['type']) ? $payload['type'] : 1;

        if($login_type == 1){
            $validator = Validator::make($payload, [
                'username' => 'required|string',
                'password' => 'required|string|min:8',
            ]);
        }else{
            $validator = Validator::make($payload, [
                'email' => 'required|email',
                'password' => 'required|string|min:8',
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'message' => 'Please provide valid login details',
                'details' => $validator->errors()
            ], 422);
        }

        if($login_type == 1){
            $user = User::findByUsername($payload['username']);
        }else{
            $user = User::findByEmail($payload['email']);
        }

        // NO user found
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Account not found',
                'message' => $login_type == 1 ? 'We could not find an account with username "' . $payload['username'] . '"' : 
                                                'We could not find an account with email "' . $payload['email'] . '".', 
            ], 401);
        }

        // $user->changePassword($payload['password']);


        // Check if account is locked
        if ($user->isLocked()) {
            $lockedTime = strtotime($user->locked_at);
            $currentTime = time();
            $timeDiff = ($currentTime - $lockedTime) / 60; 

            $lockout_duration = Settings::get('lockout_duration');
            if ($user->locked_at && $timeDiff < $lockout_duration) {
                return response()->json([
                    'success' => false,
                    'error' => 'Account locked',
                    'message' => 'Your account has been locked. Please try again later in ' . round($lockout_duration - $timeDiff) . ' minutes.'
                ], 403);
            }else{
                $user->unlockAccount();
            }
        }

        // Verify password
        if (!$user->verifyPassword($request->password)) {
            $user->incrementInvalidAttempts();
            
            $system_max_attempt = Settings::get('max_login_attempts');
            $remainingAttempts = $system_max_attempt - $user->invalid_attempts;
            $message = $remainingAttempts > 0 
                ? "Invalid password. You have {$remainingAttempts} attempts remaining."
                : 'Account locked due to too many failed attempts.';
            
            return response()->json([
                'success' => false,
                'error' => 'Invalid credentials',
                'message' => $message,
                // 'remaining_attempts' => max(0, $remainingAttempts)
            ], 401);
        }




        // return response()->json([
        //     'success' => true,
        //     'message' => 'Login successful',
        //     'user' => $user,
        //     'payload' => $payload
        // ]);



        // Check if user can login
        // if (!$user->canLogin()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Please accept terms and conditions.'
        //     ], 403);
        // }

        // Check if 2FA is enabled
        // if ($user->has2FAEnabled()) {
        //     $temp_token = bin2hex(random_bytes(32));
        //     // Store temp token in cache or session for 2FA verification
        //     cache()->put("2fa_temp_{$temp_token}", $user->ID, now()->addMinutes(10));
            
        //     return response()->json([
        //         'success' => true,
        //         'message' => '2FA verification required',
        //         'requires_2fa' => true,
        //         'temp_token' => $temp_token
        //     ], 200);
        // }

        // Create token
        // $token = $user->createToken('auth_token')->plainTextToken;
        $user->updateLastLogin();

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->ID,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->getRoleName(),
                    'profile_complete' => $user->isProfileComplete(),
                    'with_2fa' => $user->has2FAEnabled(),
                ],
                // 'token' => $token,
                // 'token_type' => 'Bearer'
            ]
        ], 200);
    }

    /**
     * Verify 2FA code
     * POST /api/auth/verify-2fa
     */
    public function verify2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_token' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = cache()->get("2fa_temp_{$request->temp_token}");
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Verify 2FA code
        // TODO: Implement actual 2FA verification
        $isValid = $request->code === '123456'; // Placeholder

        if (!$isValid) {
            $user->incrementInvalidAttempts();
            return response()->json([
                'success' => false,
                'message' => 'Invalid 2FA code'
            ], 401);
        }

        // Remove temp token
        cache()->forget("2fa_temp_{$request->temp_token}");

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->updateLastLogin();

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->ID,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->getRoleName(),
                    'profile_complete' => $user->isProfileComplete(),
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    /**
     * User registration
     * POST /api/auth/register
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:100|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'agree_terms' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = User::createUser([
                'username' => $request->username,
                'email' => $request->email,
                'password' => $request->password,
                'agree_terms' => 1,
                'role_id' => 2, // Default user role
                'created_by' => 'api',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->updateLastLogin();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => [
                        'id' => $user->ID,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->getRoleName(),
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * User logout
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ], 200);
    }

    /**
     * Refresh token
     * POST /api/auth/refresh
     */
    public function refreshToken(Request $request)
    {
        $user = $request->user();
        
        // Delete old token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    // ==================== USER PROFILE ====================

    /**
     * Get authenticated user profile
     * GET /api/user/profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->ID,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->getRoleName(),
                'role_id' => $user->role_id,
                'profile_complete' => $user->isProfileComplete(),
                'agree_terms' => $user->hasAgreedToTerms(),
                'with_2fa' => $user->has2FAEnabled(),
                'is_locked' => $user->isLocked(),
                'last_login' => $user->last_login?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
            ]
        ], 200);
    }

    /**
     * Update user profile
     * PUT /api/user/profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|string|max:100|unique:users,username,' . $user->ID . ',ID',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->ID . ',ID',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->filled('username')) {
                $user->username = $request->username;
            }
            
            if ($request->filled('email')) {
                $user->email = $request->email;
            }

            $user->updated_by = $user->username;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->ID,
                    'username' => $user->username,
                    'email' => $user->email,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete user profile
     * POST /api/user/profile/complete
     */
    public function completeProfile(Request $request)
    {
        $user = $request->user();

        if ($user->markProfileComplete($user->username)) {
            return response()->json([
                'success' => true,
                'message' => 'Profile marked as complete'
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to complete profile'
        ], 500);
    }

    // ==================== PASSWORD MANAGEMENT ====================

    /**
     * Change password
     * POST /api/user/change-password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed|different:current_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!$user->updatePassword($request->current_password, $request->new_password, $user->username)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 401);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    /**
     * Request password reset
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // TODO: Generate and send password reset token via email

        return response()->json([
            'success' => true,
            'message' => 'Password reset link has been sent to your email'
        ], 200);
    }

    /**
     * Reset password with token
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // TODO: Verify reset token
        $user = User::findByEmail($request->email);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->changePassword($request->password, 'password_reset');

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully'
        ], 200);
    }

    // ==================== 2FA MANAGEMENT ====================

    /**
     * Enable 2FA
     * POST /api/user/2fa/enable
     */
    public function enable2FA(Request $request)
    {
        $user = $request->user();

        // TODO: Generate 2FA secret and return QR code data

        if ($user->enable2FA($user->username)) {
            return response()->json([
                'success' => true,
                'message' => '2FA enabled successfully',
                'data' => [
                    'secret' => 'placeholder_secret', // TODO: Return actual secret
                    'qr_code_url' => 'placeholder_qr_url' // TODO: Return QR code
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to enable 2FA'
        ], 500);
    }

    /**
     * Disable 2FA
     * POST /api/user/2fa/disable
     */
    public function disable2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!$user->verifyPassword($request->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password'
            ], 401);
        }

        if ($user->disable2FA($user->username)) {
            return response()->json([
                'success' => true,
                'message' => '2FA disabled successfully'
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to disable 2FA'
        ], 500);
    }

    // ==================== ADMIN ENDPOINTS ====================

    /**
     * Get all users (Admin only)
     * GET /api/admin/users
     */
    public function getAllUsers(Request $request)
    {
        $query = User::with('role');

        // Apply filters
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'locked') {
                $query->locked();
            }
        }

        if ($request->filled('role')) {
            $query->byRole($request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users
        ], 200);
    }

    /**
     * Get user by ID (Admin only)
     * GET /api/admin/users/{id}
     */
    public function getUserById($id)
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->ID,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->getRoleName(),
                'role_id' => $user->role_id,
                'profile_complete' => $user->isProfileComplete(),
                'agree_terms' => $user->hasAgreedToTerms(),
                'with_2fa' => $user->has2FAEnabled(),
                'is_locked' => $user->isLocked(),
                'invalid_attempts' => $user->invalid_attempts,
                'last_login' => $user->last_login?->toIso8601String(),
                'locked_at' => $user->locked_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ]
        ], 200);
    }

    /**
     * Lock user account (Admin only)
     * POST /api/admin/users/{id}/lock
     */
    public function lockUser(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $admin = $request->user();

        if ($user->lockAccount()) {
            $user->updated_by = $admin->username;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'User account locked successfully'
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to lock user account'
        ], 500);
    }

    /**
     * Unlock user account (Admin only)
     * POST /api/admin/users/{id}/unlock
     */
    public function unlockUser(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $admin = $request->user();

        if ($user->unlockAccount($admin->username)) {
            return response()->json([
                'success' => true,
                'message' => 'User account unlocked successfully'
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to unlock user account'
        ], 500);
    }

    /**
     * Delete user (Admin only)
     * DELETE /api/admin/users/{id}
     */
    public function deleteUser(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $currentUser = $request->user();

        // Prevent self-deletion
        if ($user->ID === $currentUser->ID) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics (Admin only)
     * GET /api/admin/users/statistics
     */
    public function getStatistics()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::active()->count(),
            'locked_users' => User::locked()->count(),
            'users_with_2fa' => User::with2FA()->count(),
            'incomplete_profiles' => User::where('profile_complete', 0)->count(),
            'recent_logins' => User::whereNotNull('last_login')
                                   ->where('last_login', '>=', now()->subDays(7))
                                   ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ], 200);
    }
}