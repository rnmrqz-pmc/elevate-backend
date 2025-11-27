<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    // ==================== AUTHENTICATION ====================

    /**
     * Display login form
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle user login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = User::findByEmail($request->email);

        if (!$user) {
            return redirect()->back()
                ->withErrors(['email' => 'Invalid credentials'])
                ->withInput();
        }

        // Check if account is locked
        if ($user->isLocked()) {
            return redirect()->back()
                ->withErrors(['email' => 'Your account has been locked due to multiple failed login attempts. Please contact support.'])
                ->withInput();
        }

        // Verify password
        if (!$user->verifyPassword($request->password)) {
            $user->incrementInvalidAttempts();
            
            $remainingAttempts = 5 - $user->invalid_attempts;
            $message = $remainingAttempts > 0 
                ? "Invalid credentials. You have {$remainingAttempts} attempts remaining."
                : 'Account locked due to too many failed attempts.';
            
            return redirect()->back()
                ->withErrors(['email' => $message])
                ->withInput();
        }

        // Check if user can login
        if (!$user->canLogin()) {
            return redirect()->back()
                ->withErrors(['email' => 'Please accept terms and conditions before logging in.'])
                ->withInput();
        }

        // Check if 2FA is required
        if ($user->has2FAEnabled()) {
            session(['2fa_user_id' => $user->ID]);
            return redirect()->route('2fa.verify');
        }

        // Login successful
        Auth::loginUsingId($user->ID, $request->filled('remember'));
        $user->updateLastLogin();

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome back, ' . $user->getDisplayName() . '!');
    }

    /**
     * Display 2FA verification form
     */
    public function show2FAForm()
    {
        if (!session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.2fa-verify');
    }

    /**
     * Verify 2FA code
     */
    public function verify2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        $userId = session('2fa_user_id');
        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('login')
                ->withErrors(['error' => 'Session expired. Please login again.']);
        }

        // TODO: Implement actual 2FA verification logic here
        // This is a placeholder - you should verify against stored 2FA secret
        $isValid = $this->verify2FACode($user, $request->code);

        if (!$isValid) {
            $user->incrementInvalidAttempts();
            return redirect()->back()
                ->withErrors(['code' => 'Invalid 2FA code']);
        }

        // 2FA verified, complete login
        Auth::loginUsingId($user->ID);
        $user->updateLastLogin();
        session()->forget('2fa_user_id');

        return redirect()->route('dashboard')
            ->with('success', 'Welcome back, ' . $user->getDisplayName() . '!');
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out successfully.');
    }

    // ==================== REGISTRATION ====================

    /**
     * Display registration form
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle user registration
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
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $user = User::createUser([
                'username' => $request->username,
                'email' => $request->email,
                'password' => $request->password,
                'agree_terms' => 1,
                'role_id' => 2, // Default user role
                'created_by' => 'system',
            ]);

            DB::commit();

            // Auto login after registration
            Auth::loginUsingId($user->ID);
            $user->updateLastLogin();

            return redirect()->route('profile.setup')
                ->with('success', 'Registration successful! Please complete your profile.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withErrors(['error' => 'Registration failed. Please try again.'])
                ->withInput();
        }
    }

    // ==================== PASSWORD MANAGEMENT ====================

    /**
     * Display forgot password form
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset link
     */
    public function sendPasswordResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = User::findByEmail($request->email);

        // TODO: Generate and send password reset token via email
        // This is a placeholder implementation

        return redirect()->back()
            ->with('success', 'Password reset link has been sent to your email.');
    }

    /**
     * Display reset password form
     */
    public function showResetPasswordForm($token)
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // TODO: Verify reset token
        $user = User::findByEmail($request->email);

        if (!$user) {
            return redirect()->back()
                ->withErrors(['email' => 'User not found.']);
        }

        $user->changePassword($request->password, 'password_reset');

        return redirect()->route('login')
            ->with('success', 'Password has been reset successfully. Please login with your new password.');
    }

    /**
     * Display change password form
     */
    public function showChangePasswordForm()
    {
        return view('auth.change-password');
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed|different:current_password',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        $user = Auth::user();

        if (!$user->updatePassword($request->current_password, $request->new_password, $user->username)) {
            return redirect()->back()
                ->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        return redirect()->back()
            ->with('success', 'Password changed successfully.');
    }

    // ==================== USER PROFILE ====================

    /**
     * Display user profile
     */
    public function showProfile()
    {
        $user = Auth::user();
        return view('user.profile', compact('user'));
    }

    /**
     * Display profile setup form
     */
    public function showProfileSetup()
    {
        $user = Auth::user();
        return view('user.profile-setup', compact('user'));
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:100|unique:users,username,' . $user->ID . ',ID',
            'email' => 'required|email|max:255|unique:users,email,' . $user->ID . ',ID',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $user->username = $request->username;
            $user->email = $request->email;
            $user->updated_by = $user->username;
            $user->save();

            return redirect()->back()
                ->with('success', 'Profile updated successfully.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to update profile.'])
                ->withInput();
        }
    }

    /**
     * Mark profile as complete
     */
    public function completeProfile(Request $request)
    {
        $user = Auth::user();
        
        if ($user->markProfileComplete($user->username)) {
            return redirect()->route('dashboard')
                ->with('success', 'Profile completed successfully!');
        }

        return redirect()->back()
            ->withErrors(['error' => 'Failed to complete profile.']);
    }

    // ==================== ACCOUNT MANAGEMENT ====================

    /**
     * Display all users (admin)
     */
    public function index(Request $request)
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

        $users = $query->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Display single user details (admin)
     */
    public function show($id)
    {
        $user = User::with('role')->findOrFail($id);
        return view('admin.users.show', compact('user'));
    }

    /**
     * Lock user account (admin)
     */
    public function lockAccount($id)
    {
        $user = User::findOrFail($id);
        $admin = Auth::user();

        if ($user->lockAccount()) {
            $user->updated_by = $admin->username;
            $user->save();

            return redirect()->back()
                ->with('success', 'User account locked successfully.');
        }

        return redirect()->back()
            ->withErrors(['error' => 'Failed to lock user account.']);
    }

    /**
     * Unlock user account (admin)
     */
    public function unlockAccount($id)
    {
        $user = User::findOrFail($id);
        $admin = Auth::user();

        if ($user->unlockAccount($admin->username)) {
            return redirect()->back()
                ->with('success', 'User account unlocked successfully.');
        }

        return redirect()->back()
            ->withErrors(['error' => 'Failed to unlock user account.']);
    }

    /**
     * Delete user account (admin)
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $currentUser = Auth::user();

        // Prevent self-deletion
        if ($user->ID === $currentUser->ID) {
            return redirect()->back()
                ->withErrors(['error' => 'You cannot delete your own account.']);
        }

        try {
            $user->delete();

            return redirect()->route('admin.users.index')
                ->with('success', 'User deleted successfully.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete user.']);
        }
    }

    // ==================== 2FA MANAGEMENT ====================

    /**
     * Display 2FA settings
     */
    public function show2FASettings()
    {
        $user = Auth::user();
        return view('user.2fa-settings', compact('user'));
    }

    /**
     * Enable 2FA
     */
    public function enable2FA(Request $request)
    {
        $user = Auth::user();

        // TODO: Generate 2FA secret and QR code
        // This is a placeholder implementation

        if ($user->enable2FA($user->username)) {
            return redirect()->back()
                ->with('success', '2FA has been enabled successfully.');
        }

        return redirect()->back()
            ->withErrors(['error' => 'Failed to enable 2FA.']);
    }

    /**
     * Disable 2FA
     */
    public function disable2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        $user = Auth::user();

        if (!$user->verifyPassword($request->password)) {
            return redirect()->back()
                ->withErrors(['password' => 'Invalid password.']);
        }

        if ($user->disable2FA($user->username)) {
            return redirect()->back()
                ->with('success', '2FA has been disabled.');
        }

        return redirect()->back()
            ->withErrors(['error' => 'Failed to disable 2FA.']);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Verify 2FA code (placeholder)
     */
    private function verify2FACode(User $user, string $code): bool
    {
        // TODO: Implement actual 2FA verification
        // This should verify against a TOTP secret stored for the user
        // For now, returning true for demonstration
        return $code === '123456'; // Placeholder
    }

    /**
     * Get user statistics (admin)
     */
    public function statistics()
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

        return view('admin.users.statistics', compact('stats'));
    }
}