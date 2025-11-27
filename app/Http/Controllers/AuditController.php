<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Support\Settings;


class AuditController extends Controller
{


    public function getLoginAttempts(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $loginAttempts = User::find($user->id)->loginAttempts()->get();

        return response()->json($loginAttempts);
    }

    public function clearLoginAttempts(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        User::find($user->id)->loginAttempts()->delete();

        return response()->json(['message' => 'Login attempts cleared successfully']);
    }
}