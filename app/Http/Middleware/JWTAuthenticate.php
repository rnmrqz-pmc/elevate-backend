<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;

class JWTAuthenticate
{
    public function handle(Request $request, Closure $next)
    {        
        $token = $request->cookie('__RequestVerificationToken') ?? $request->bearerToken();
                
        if (!$token) {
            return response()->json([
                'status' => false,
                'error' => 'Token not found',
                'message' => 'Token not found in request cookies or Authorization header'
            ], 401);
        }

        try {
            JWTAuth::setToken($token);
            $user = JWTAuth::authenticate();
            
            // Log::info('User authenticated:', ['user_id' => $user->id]);
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
            
        } catch (TokenExpiredException $e) {
            // Log::error('Token expired');
            return response()->json([
                'status' => false,
                'error' => 'Token has expired',
                'message' => $e->getMessage()
            ], 401);
            
        } catch (TokenInvalidException $e) {
            // Log::error('Token invalid');
            return response()->json([
                'status' => false,
                'error' => 'Token is invalid',
                'message' => $e->getMessage()
            ], 401);
            
        } catch (JWTException $e) {
            // Log::error('JWT Exception:', ['message' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'error' => 'Token not found or malformed',
                'message' => $e->getMessage()
            ], 401);
            
        } catch (\Exception $e) {
            // Log::error('General Exception:', ['message' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'error' => 'Authorization error',
                'message' => $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}