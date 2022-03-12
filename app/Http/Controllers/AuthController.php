<?php

namespace App\Http\Controllers;

use App\services\AuthenticationServices;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request): \Illuminate\Http\JsonResponse
    {
        return AuthenticationServices::signUp($request);
    }

    public function login(Request $request)
    {
        return AuthenticationServices::signIn($request);
    }

    public function forgotPassword(Request $request)
    {
        return AuthenticationServices::forgotPassword($request);
    }

    public function resetPassword(Request $request)
    {
        return AuthenticationServices::resetPassword($request);
    }
}
