<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Helpers\Security;
use App\Models\User;


class Authentication extends Controller
{
    private $isDev = false;

    public function __construct(Request $request){
        $apiKey = $request->header('x-api-key');   
        if($apiKey === env('DEV_KEY')){ $this->isDev = true; }
    }


    

}
