<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;




Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    // Route::get('profile', [AuthController::class, 'getProfile']);

    Route::middleware('jwt')->group(function () {
        Route::get('profile', [AuthController::class, 'getProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refreshToken']);
    });
});

Route::post('test-email', [MailController::class, 'sendMultipleEmails']);




// /*
// |--------------------------------------------------------------------------
// | API Routes
// |--------------------------------------------------------------------------
// */

// // ==================== PUBLIC ROUTES (No Authentication Required) ====================


// Route::prefix('auth')->group(function () {
//     // Authentication
//     Route::post('/login', [ApiUserController::class, 'login']);
//     Route::post('/register', [ApiUserController::class, 'register']);
//     Route::post('/verify-2fa', [ApiUserController::class, 'verify2FA']);
    
//     // Password Reset
//     Route::post('/forgot-password', [ApiUserController::class, 'forgotPassword']);
//     Route::post('/reset-password', [ApiUserController::class, 'resetPassword']);
// });

// // ==================== PROTECTED ROUTES (Authentication Required) ====================

// Route::middleware('auth:sanctum')->group(function () {
    
//     // ==================== AUTH ROUTES ====================
//     Route::prefix('auth')->group(function () {
//         Route::post('/logout', [ApiUserController::class, 'logout']);
//         Route::post('/refresh', [ApiUserController::class, 'refreshToken']);
//     });
    
//     // ==================== USER PROFILE ROUTES ====================
//     Route::prefix('user')->group(function () {
//         // Profile Management
//         Route::get('/profile', [ApiUserController::class, 'getProfile']);
//         Route::put('/profile', [ApiUserController::class, 'updateProfile']);
//         Route::post('/profile/complete', [ApiUserController::class, 'completeProfile']);
        
//         // Password Management
//         Route::post('/change-password', [ApiUserController::class, 'changePassword']);
        
//         // 2FA Management
//         Route::prefix('2fa')->group(function () {
//             Route::post('/enable', [ApiUserController::class, 'enable2FA']);
//             Route::post('/disable', [ApiUserController::class, 'disable2FA']);
//         });
//     });
    
//     // ==================== ADMIN ROUTES ====================
//     // Note: Add admin middleware to these routes in production
//     Route::prefix('admin')->group(function () {
//         // User Management
//         Route::get('/users', [ApiUserController::class, 'getAllUsers']);
//         Route::get('/users/statistics', [ApiUserController::class, 'getStatistics']);
//         Route::get('/users/{id}', [ApiUserController::class, 'getUserById']);
//         Route::post('/users/{id}/lock', [ApiUserController::class, 'lockUser']);
//         Route::post('/users/{id}/unlock', [ApiUserController::class, 'unlockUser']);
//         Route::delete('/users/{id}', [ApiUserController::class, 'deleteUser']);
//     });
// });

// // ==================== FALLBACK ROUTE ====================
// Route::fallback(function () {
//     return response()->json([
//         'success' => false,
//         'message' => 'Endpoint not found'
//     ], 404);
// });