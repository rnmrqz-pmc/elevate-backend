<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SysLoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    // Register new user
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'username'     => $request->username,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
        ]);

        return response()->json(['message' => 'User registered successfully', 'user' => $user], 201);
    }

    // Login user and return JWT token
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
                'status' => false,
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
                'status' => false,
                'error' => 'Account not found',
                'message' => $login_type == 1 ? 'We could not find an account with username ' . $payload['username'] : 
                                                'We could not find an account with email ' . $payload['email'], 
            ], 404);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            $lockedTime = strtotime($user->locked_at);
            $currentTime = time();
            $timeDiff = ($currentTime - $lockedTime) / 60; 

            $lockout_duration = Settings::get('lockout_duration');
            if ($user->locked_at && $timeDiff < $lockout_duration) {
                SysLoginAttempt::insert([
                    'user_id' => $user->ID,
                    'username' => $user->username,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'success' => 0,
                    'fail_reason' => 'Account locked',
                ]);
                return response()->json([
                    'status' => false,
                    'error' => 'Account locked',
                    'message' => 'Your account has been locked. Please try again later in ' . round($lockout_duration - $timeDiff) . ' minutes.'
                ], 423);
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
            
            SysLoginAttempt::insert([
                'user_id' => $user->ID,
                'username' => $user->username,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'success' => 0,
                'fail_reason' => 'Invalid password',
            ]);
            return response()->json([
                'status' => false,
                'error' => 'Invalid credentials',
                'message' => $message,
                // 'remaining_attempts' => max(0, $remainingAttempts)
            ], 401);
        }

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
        $auth_token = Auth::login($user);
        $auth_user = Auth::user(); 
        $customClaims = ['iss' => 'api'];
        $auth_token = auth()->claims($customClaims)->login($auth_user);

        $user->updateLastLogin();
        $valid_cookie = cookie(
            '__RequestVerificationToken',
            $auth_token,                                     
            config('jwt.ttl', 60),                           // Expiration in minutes (default 60)
            '/',                                             
            null,                                            
            config('app.env') === 'production',              
            true,                                            // HttpOnly
            false,                                           // Raw
            'strict'                                         // SameSite policy (lax, strict, or none)
        );
        SysLoginAttempt::insert([
            'user_id' => $user->ID,
            'username' => $user->username,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'success' => 1,
            'fail_reason' => '',
        ]);
        return response()->json([
            'status' => true,
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
                // 'token' => $auth_token,
            ]
        ], 200)->withCookie($valid_cookie);
    }



    // Get user profile
    public function getProfile(Request $request) {
        Log::info('=== PROFILE ENDPOINT HIT ===');

        // $user = Auth::user();
        $user = $request->user();

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $user->ID,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->getRoleName(),
                'role_id' => $user->role_id,
                'profile_complete' => $user->isProfileComplete(),
                // 'agree_terms' => $user->hasAgreedToTerms(),
                'with_2fa' => $user->has2FAEnabled(),
                'is_locked' => $user->isLocked(),
                'last_login' => $user->last_login,
                'created_at' => $user->created_at,
            ]
        ], 200);
    }

    public function logout() {
        Auth::logout();
        $cookie = cookie()->forget('__RequestVerificationToken');
        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'], 200)->withCookie($cookie);   
    }

    // Refresh JWT token
    public function refreshToken(Request $request) {
        $token = $request->cookie('__RequestVerificationToken') ?? $request->bearerToken();
        JWTAuth::setToken($token);
        $newToken = JWTAuth::refresh();
             
        $valid_cookie = cookie(
            '__RequestVerificationToken',
            $newToken,                                     
            env('JWT_TTL', 120),                           // Expiration in minutes (default 60)
            '/',                                             
            null,                                            
            config('app.env') === 'production',              
            true,                                            // HttpOnly
            false,                                           // Raw
            'strict'                                         // SameSite policy (lax, strict, or none)
        );

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            // 'data' => [
            //     'token' => $newToken,
            //     'token_type' => 'Bearer'
            // ]
        ], 200)
        // ->withCookie($valid_cookie)
        ;
    }

    // Return token response structure
    protected function respondWithToken($token) {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
