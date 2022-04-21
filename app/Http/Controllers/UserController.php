<?php

namespace App\Http\Controllers;

use App\Services\UserServices;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function register(Request $request)
    {
        return UserServices::register($request);
    }

    public function signIn(Request $request)
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
