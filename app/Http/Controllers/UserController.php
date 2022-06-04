<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserServices;
use App\services\AuthenticationServices;

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

    public function transactionHistory(Request $request)
    {
        return UserServices::transactionHistory($request);
    }


}
